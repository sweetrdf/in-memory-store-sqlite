<?php

/*
 *  This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;

class ARC2_Store extends ARC2_Class
{
    protected $cache;
    protected $db;

    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);

        $this->db = new PDOSQLiteAdapter();

        $this->a['db_object'] = $this->db;
    }

    public function __init()
    {
        parent::__init();
        $this->table_lock = 0;
        $this->triggers = $this->v('store_triggers', [], $this->a);
        $this->queue_queries = $this->v('store_queue_queries', 0, $this->a);
        $this->is_win = ('win' == strtolower(substr(PHP_OS, 0, 3))) ? true : false;
        $this->max_split_tables = $this->v('store_max_split_tables', 10, $this->a);
        $this->split_predicates = $this->v('store_split_predicates', [], $this->a);
    }

    public function cacheEnabled()
    {
        return isset($this->a['cache_enabled'])
            && true === $this->a['cache_enabled']
            && 'pdo' == $this->a['db_adapter'];
    }

    public function getName()
    {
        return $this->v('store_name', 'arc', $this->a);
    }

    /**
     * @todo remove
     */
    public function createDBCon()
    {
        return true;
    }

    public function getDBObject(): ?PDOSQLiteAdapter
    {
        return $this->db;
    }

    /**
     * @todo remove
     */
    public function getDBCon($force = 0)
    {
        return true;
    }

    public function getDBVersion()
    {
        return $this->db->getServerVersion();
    }

    /**
     * @return string Returns DBS name. Possible values: mysql, mariadb
     */
    public function getDBSName()
    {
        return $this->db->getDBSName();
    }

    public function getCollation()
    {
        $row = $this->db->fetchRow('SHOW TABLE STATUS LIKE "setting"');

        return isset($row['Collation']) ? $row['Collation'] : '';
    }

    public function getColumnType()
    {
        if (!$this->v('column_type')) {
            $this->column_type = 'INTEGER';
        }

        return $this->column_type;
    }

    public function hasHashColumn($tbl)
    {
        $var_name = 'has_hash_column_'.$tbl;
        if (!isset($this->$var_name)) {
            $this->$var_name = true;
        }

        return $this->$var_name;
    }

    /**
     * @todo remove
     */
    public function hasFulltextIndex()
    {
        return true;
    }

    /**
     * @todo remove
     */
    public function enableFulltextSearch()
    {
    }

    /**
     * @todo remove
     */
    public function disableFulltextSearch()
    {
    }

    public function getTables()
    {
        return ['triple', 'g2t', 'id2val', 's2val', 'o2val', 'setting'];
    }

    /**
     * @todo remove
     */
    public function extendColumns()
    {
    }

    /**
     * @todo remove
     */
    public function splitTables()
    {
    }

    public function hasSetting($k)
    {
        if (null == $this->db) {
            $this->createDBCon();
        }

        $tbl = 'setting';

        return $this->db->fetchRow('SELECT val FROM '.$tbl." WHERE k = '".md5($k)."'")
            ? 1
            : 0;
    }

    public function getSetting($k, $default = 0)
    {
        if (null == $this->db) {
            $this->createDBCon();
        }

        $tbl = 'setting';
        $row = $this->db->fetchRow('SELECT val FROM '.$tbl." WHERE k = '".md5($k)."'");
        if (isset($row['val'])) {
            return unserialize($row['val']);
        }

        return $default;
    }

    public function setSetting($k, $v)
    {
        $tbl = 'setting';
        if ($this->hasSetting($k)) {
            $sql = 'UPDATE '.$tbl." SET val = '".$this->db->escape(serialize($v))."' WHERE k = '".md5($k)."'";
        } else {
            $sql = 'INSERT INTO '.$tbl." (k, val) VALUES ('".md5($k)."', '".$this->db->escape(serialize($v))."')";
        }

        return $this->db->simpleQuery($sql);
    }

    public function removeSetting($k)
    {
        $tbl = 'setting';

        return $this->db->simpleQuery('DELETE FROM '.$tbl." WHERE k = '".md5($k)."'");
    }

    public function getQueueTicket()
    {
        if (!$this->queue_queries) {
            return 1;
        }
        $t = 'ticket_'.substr(md5(uniqid(rand())), 0, 10);
        /* lock */
        $this->db->simpleQuery('LOCK TABLES setting WRITE');
        /* queue */
        $queue = $this->getSetting('query_queue', []);
        $queue[] = $t;
        $this->setSetting('query_queue', $queue);
        $this->db->simpleQuery('UNLOCK TABLES');
        /* loop */
        $lc = 0;
        $queue = $this->getSetting('query_queue', []);
        while ($queue && ($queue[0] != $t) && ($lc < 30)) {
            usleep(100000);
            $lc += 0.1;
            $queue = $this->getSetting('query_queue', []);
        }

        return ($lc < 30) ? $t : 0;
    }

    public function removeQueueTicket($t)
    {
        if (!$this->queue_queries) {
            return 1;
        }
        /* lock */
        $this->db->simpleQuery('LOCK TABLES setting WRITE');
        /* queue */
        $vals = $this->getSetting('query_queue', []);
        $pos = array_search($t, $vals);
        $queue = ($pos < (count($vals) - 1)) ? array_slice($vals, $pos + 1) : [];
        $this->setSetting('query_queue', $queue);
        $this->db->simpleQuery('UNLOCK TABLES');
    }

    public function reset($keep_settings = 0)
    {
        $tbls = $this->getTables();
        /* remove split tables */
        $ps = $this->getSetting('split_predicates', []);
        foreach ($ps as $p) {
            $tbl = 'triple_'.abs(crc32($p));
            $this->db->simpleQuery('DROP TABLE '.$tbl);
        }
        $this->removeSetting('split_predicates');
        /* truncate tables */
        foreach ($tbls as $tbl) {
            if ($keep_settings && ('setting' == $tbl)) {
                continue;
            }
            $this->db->simpleQuery('DELETE FROM '.$tbl);
        }
    }

    public function insert($doc, $g, $keep_bnode_ids = 0)
    {
        $doc = is_array($doc) ? $this->toTurtle($doc) : $doc;
        $infos = ['query' => ['url' => $g, 'target_graph' => $g]];
        $h = new ARC2_StoreLoadQueryHandler($this->a, $this);
        $r = $h->runQuery($infos, $doc, $keep_bnode_ids);
        $this->processTriggers('insert', $infos);

        return $r;
    }

    public function delete($doc, $g)
    {
        if (!$doc) {
            $infos = ['query' => ['target_graphs' => [$g]]];
            $h = new ARC2_StoreDeleteQueryHandler($this->a, $this);
            $r = $h->runQuery($infos);
            $this->processTriggers('delete', $infos);

            return $r;
        }
    }

    public function dump()
    {
        throw new Exception('Implement dump by loading all triples and create a RDF file.');
    }

    /**
     * Executes a SPARQL query.
     *
     * @param string $q              SPARQL query
     * @param string $result_format  Possible values: infos, raw, rows, row
     * @param string $src
     * @param int    $keep_bnode_ids Keep blank node IDs? Default is 0
     *
     * @return array|int array if query returned a result, 0 otherwise
     */
    public function query($q, $result_format = '', $src = '', $keep_bnode_ids = 0)
    {
        if (preg_match('/^dump/i', $q)) {
            $infos = ['query' => ['type' => 'dump']];
        } else {
            // check cache
            $key = hash('sha1', $q);
            if ($this->cacheEnabled() && $this->cache->has($key.'_infos')) {
                $infos = $this->cache->get($key.'_infos');
                $errors = $this->cache->get($key.'_errors');
            // no entry found
            } else {
                $p = new ARC2_SPARQLPlusParser($this->a, $this);
                $p->parse($q, $src);
                $infos = $p->getQueryInfos();
                $errors = $p->getErrors();

                // store result in cache
                if ($this->cacheEnabled()) {
                    $this->cache->set($key.'_infos', $infos);
                    $this->cache->set($key.'_errors', $errors);
                }
            }
        }

        if ('infos' == $result_format) {
            return $infos;
        }

        $infos['result_format'] = $result_format;

        if (!isset($p) || 0 == count($errors)) {
            $qt = $infos['query']['type'];
            if (!in_array($qt, ['select', 'ask', 'describe', 'construct', 'load', 'insert', 'delete', 'dump'])) {
                return $this->addError('Unsupported query type "'.$qt.'"');
            }
            // if cache is enabled, get/store result
            $key = hash('sha1', $q);
            if ($this->cacheEnabled() && $this->cache->has($key)) {
                $result = $this->cache->get($key);
            } else {
                $result = $this->runQuery($infos, $qt, $keep_bnode_ids, $q);

                // store in cache, if enabled
                if ($this->cacheEnabled()) {
                    $this->cache->set($key, $result);
                }
            }

            $r = ['query_type' => $qt, 'result' => $result];
            $r['query_time'] = 0;

            /* query result */
            if ('raw' == $result_format) {
                return $r['result'];
            }
            if ('rows' == $result_format) {
                return $r['result']['rows'] ? $r['result']['rows'] : [];
            }
            if ('row' == $result_format) {
                return $r['result']['rows'] ? $r['result']['rows'][0] : [];
            }

            return $r;
        }

        return 0;
    }

    /**
     * Uses a relevant QueryHandler class to handle given $query.
     */
    private function runQuery($infos, $type, $keep_bnode_ids = 0, $q = '')
    {
        $cls = 'ARC2_Store'.ucfirst($type).'QueryHandler';
        $h = new $cls($this->a, $this);
        $ticket = 1;
        $r = [];
        if ($q && ('select' == $type)) {
            $ticket = $this->getQueueTicket($q);
        }
        if ($ticket) {
            if ('load' == $type) {/* the LoadQH supports raw data as 2nd parameter */
                $r = $h->runQuery($infos, '', $keep_bnode_ids);
            } else {
                $r = $h->runQuery($infos, $keep_bnode_ids);
            }
        }
        if ($q && ('select' == $type)) {
            $this->removeQueueTicket($ticket);
        }
        $this->processTriggers($type, $infos);

        return $r;
    }

    public function processTriggers($type, $infos)
    {
        $r = [];
        $trigger_defs = $this->triggers;
        $this->triggers = [];
        $triggers = $this->v($type, [], $trigger_defs);
        if ($triggers) {
            $r['trigger_results'] = [];
            $triggers = is_array($triggers) ? $triggers : [$triggers];
            foreach ($triggers as $trigger) {
                $trigger .= !preg_match('/Trigger$/', $trigger) ? 'Trigger' : '';
                $cls = 'ARC2_'.ucfirst($trigger);
                $config = array_merge($this->a, ['query_infos' => $infos]);
                $trigger_obj = new $cls($config, $this);
                if (method_exists($trigger_obj, 'go')) {
                    $r['trigger_results'][] = $trigger_obj->go();
                }
            }
        }
        $this->triggers = $trigger_defs;

        return $r;
    }

    public function getValueHash($val, $_32bit = false)
    {
        $hash = crc32($val);
        if ($_32bit && ($hash & 0x80000000)) {
            $hash = sprintf('%u', $hash);
        }
        $hash = abs($hash);

        return $hash;
    }

    public function getTermID($val, $term = '')
    {
        /* mem cache */
        if (!isset($this->term_id_cache) || (count(array_keys($this->term_id_cache)) > 100)) {
            $this->term_id_cache = [];
        }
        if (!isset($this->term_id_cache[$term])) {
            $this->term_id_cache[$term] = [];
        }
        $tbl = preg_match('/^(s|o)$/', $term) ? $term.'2val' : 'id2val';
        /* cached? */
        if ((strlen($val) < 100) && isset($this->term_id_cache[$term][$val])) {
            return $this->term_id_cache[$term][$val];
        }
        $r = 0;
        /* via hash */
        if (preg_match('/^(s2val|o2val)$/', $tbl) && $this->hasHashColumn($tbl)) {
            $rows = $this->db->fetchList(
                'SELECT id, val FROM '.$tbl." WHERE val_hash = '".$this->getValueHash($val)."' ORDER BY id"
            );
            if (is_array($rows) && 0 < count($rows)) {
                foreach ($rows as $row) {
                    if ($row['val'] == $val) {
                        $r = $row['id'];
                        break;
                    }
                }
            }
        }
        /* exact match */
        else {
            $sql = 'SELECT id FROM '.$tbl." WHERE val = '".$this->db->escape($val)."' LIMIT 1";
            $row = $this->db->fetchRow($sql);

            if (null !== $row && isset($row['id'])) {
                $r = $row['id'];
            }
        }
        if ($r && (strlen($val) < 100)) {
            $this->term_id_cache[$term][$val] = $r;
        }

        return $r;
    }

    public function getIDValue($id, $term = '')
    {
        $tbl = preg_match('/^(s|o)$/', $term) ? $term.'2val' : 'id2val';
        $row = $this->db->fetchRow(
            'SELECT val FROM '.$tbl.' WHERE id = '.$this->db->escape($id).' LIMIT 1'
        );
        if (isset($row['val'])) {
            return $row['val'];
        }

        return 0;
    }

    /**
     * @param string $res           URI
     * @param string $unnamed_label How to label a resource without a name?
     *
     * @return string
     */
    public function getResourceLabel($res)
    {
        // init local label cache, if not set
        if (!isset($this->resource_labels)) {
            $this->resource_labels = [];
        }
        // if we already know the label for the given resource
        if (isset($this->resource_labels[$res])) {
            return $this->resource_labels[$res];
        }
        // if no URI was given, assume its a literal and return it
        if (!preg_match('/^[a-z0-9\_]+\:[^\s]+$/si', $res)) {
            return $res;
        }

        $ps = $this->getLabelProps();
        if ($this->getSetting('store_label_properties', '-') != md5(serialize($ps))) {
            $this->inferLabelProps($ps);
        }

        foreach ($ps as $labelProperty) {
            // send a query for each label property
            $result = $this->query('SELECT ?label WHERE { <'.$res.'> <'.$labelProperty.'> ?label }');
            if (isset($result['result']['rows'][0])) {
                $this->resource_labels[$res] = $result['result']['rows'][0]['label'];

                return $result['result']['rows'][0]['label'];
            }
        }

        $r = preg_replace("/^(.*[\/\#])([^\/\#]+)$/", '\\2', str_replace('#self', '', $res));
        $r = str_replace('_', ' ', $r);
        $r = preg_replace_callback('/([a-z])([A-Z])/', function ($matches) {
            return $matches[1].' '.strtolower($matches[2]);
        }, $r);

        return $r;
    }

    public function getLabelProps()
    {
        return array_merge(
            $this->v('rdf_label_properties', [], $this->a),
            [
                'http://www.w3.org/2000/01/rdf-schema#label',
                'http://xmlns.com/foaf/0.1/name',
                'http://purl.org/dc/elements/1.1/title',
                'http://purl.org/rss/1.0/title',
                'http://www.w3.org/2004/02/skos/core#prefLabel',
                'http://xmlns.com/foaf/0.1/nick',
            ]
        );
    }

    public function inferLabelProps($ps)
    {
        $this->query('DELETE FROM <label-properties>');
        $sub_q = '';
        foreach ($ps as $p) {
            $sub_q .= ' <'.$p.'> a <http://semsol.org/ns/arc#LabelProperty> . ';
        }
        $this->query('INSERT INTO <label-properties> { '.$sub_q.' }');
        $this->setSetting('store_label_properties', md5(serialize($ps)));
    }

    public function getResourcePredicates($res)
    {
        $r = [];
        $rows = $this->query('SELECT DISTINCT ?p WHERE { <'.$res.'> ?p ?o . }', 'rows');
        foreach ($rows as $row) {
            $r[$row['p']] = [];
        }

        return $r;
    }

    public function getDomains($p)
    {
        $r = [];
        foreach ($this->query('SELECT DISTINCT ?type WHERE {?s <'.$p.'> ?o ; a ?type . }', 'rows') as $row) {
            $r[] = $row['type'];
        }

        return $r;
    }

    public function getPredicateRange($p)
    {
        $row = $this->query('SELECT ?val WHERE {<'.$p.'> rdfs:range ?val . } LIMIT 1', 'row');

        return $row ? $row['val'] : '';
    }
}

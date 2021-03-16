<?php

/*
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-3 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Benjamin Nowack
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sweetrdf\InMemoryStoreSqlite\Store;

use Exception;
use sweetrdf\InMemoryStoreSqlite\Logger;
use sweetrdf\InMemoryStoreSqlite\Parser\SPARQLPlusParser;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Serializer\TurtleSerializer;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\AskQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\ConstructQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\DeleteQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\DescribeQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\InsertQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\LoadQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\SelectQueryHandler;

class InMemoryStoreSqlite
{
    protected PDOSQLiteAdapter $db;

    protected Logger $logger;

    public function __construct(PDOSQLiteAdapter $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getDBObject(): ?PDOSQLiteAdapter
    {
        return $this->db;
    }

    public function getDBVersion()
    {
        return $this->db->getServerVersion();
    }

    public function getTables()
    {
        return ['triple', 'g2t', 'id2val', 's2val', 'o2val', 'setting'];
    }

    public function hasSetting($k)
    {
        $tbl = 'setting';

        return $this->db->fetchRow('SELECT val FROM '.$tbl." WHERE k = '".md5($k)."'")
            ? 1
            : 0;
    }

    public function getSetting($k, $default = 0)
    {
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

    private function toTurtle($v): string
    {
        $ser = new TurtleSerializer();

        return (isset($v[0]) && isset($v[0]['s']))
            ? $ser->getSerializedTriples($v)
            : $ser->getSerializedIndex($v);
    }

    /**
     * @todo remove?
     */
    public function insert($data, $g, $keep_bnode_ids = 0)
    {
        if (\is_array($data)) {
            $data = $this->toTurtle($data);
        }

        if (empty($data)) {
            // TODO required to throw something here?
            return;
        }

        $infos = ['query' => ['url' => $g, 'target_graph' => $g]];
        $h = new LoadQueryHandler($this);

        return $h->runQuery($infos, $data, $keep_bnode_ids);
    }

    public function delete($doc, $g)
    {
        if (!$doc) {
            $infos = ['query' => ['target_graphs' => [$g]]];
            $h = new DeleteQueryHandler($this);
            $r = $h->runQuery($infos);

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
            $p = new SPARQLPlusParser();
            $p->parse($q, $src);
            $infos = $p->getQueryInfos();
            $errors = $p->getErrors();
        }

        if ('infos' == $result_format) {
            return $infos;
        }

        $infos['result_format'] = $result_format;

        if (!isset($p) || 0 == \count($errors)) {
            $qt = $infos['query']['type'];
            $validTypes = ['select', 'ask', 'describe', 'construct', 'load', 'insert', 'delete', 'dump'];
            if (!\in_array($qt, $validTypes)) {
                throw new Exception('Unsupported query type "'.$qt.'"');
            }

            $result = $this->runQuery($infos, $qt, $keep_bnode_ids, $q);

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
     *
     * @todo remove $keep_bnode_ids
     */
    private function runQuery(array $infos, string $type, $keep_bnode_ids = 0)
    {
        $type = ucfirst($type);

        $cls = match ($type) {
            'Ask' => AskQueryHandler::class,
            'Construct' => ConstructQueryHandler::class,
            'Describe' => DescribeQueryHandler::class,
            'Delete' => DeleteQueryHandler::class,
            'Insert' => InsertQueryHandler::class,
            'Load' => LoadQueryHandler::class,
            'Select' => SelectQueryHandler::class,
        };

        if (empty($cls)) {
            throw new Exception('Inalid query $type given.');
        }

        return (new $cls($this))->runQuery($infos);
    }

    /**
     * @param int|float|string $val
     */
    public function getValueHash($val)
    {
        return abs(crc32($val));
    }

    public function getTermID($val, $term = '')
    {
        /* mem cache */
        if (!isset($this->term_id_cache) || (\count(array_keys($this->term_id_cache)) > 100)) {
            $this->term_id_cache = [];
        }
        if (!isset($this->term_id_cache[$term])) {
            $this->term_id_cache[$term] = [];
        }
        $tbl = preg_match('/^(s|o)$/', $term) ? $term.'2val' : 'id2val';
        /* cached? */
        if ((\strlen($val) < 100) && isset($this->term_id_cache[$term][$val])) {
            return $this->term_id_cache[$term][$val];
        }
        $r = 0;
        /* via hash */
        if (preg_match('/^(s2val|o2val)$/', $tbl)) {
            $rows = $this->db->fetchList(
                'SELECT id, val FROM '.$tbl." WHERE val_hash = '".$this->getValueHash($val)."' ORDER BY id"
            );
            if (\is_array($rows) && 0 < \count($rows)) {
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
        if ($r && (\strlen($val) < 100)) {
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
        return [
            'http://www.w3.org/2000/01/rdf-schema#label',
            'http://xmlns.com/foaf/0.1/name',
            'http://purl.org/dc/elements/1.1/title',
            'http://purl.org/rss/1.0/title',
            'http://www.w3.org/2004/02/skos/core#prefLabel',
            'http://xmlns.com/foaf/0.1/nick',
        ];
    }

    public function inferLabelProps($ps)
    {
        $this->query('DELETE FROM <label-properties>');
        $sub_q = '';
        foreach ($ps as $p) {
            $sub_q .= ' <'.$p.'> a <http://semsol.org/ns/arc#LabelProperty> . ';
        }
        $this->query('INSERT INTO <label-properties> { '.$sub_q.' }');

        // TODO is that required? move to standalone property if so
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

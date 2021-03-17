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
    private PDOSQLiteAdapter $db;

    private array $labelProperties = [
        'http://www.w3.org/2000/01/rdf-schema#label',
        'http://xmlns.com/foaf/0.1/name',
        'http://purl.org/dc/elements/1.1/title',
        'http://purl.org/rss/1.0/title',
        'http://www.w3.org/2004/02/skos/core#prefLabel',
        'http://xmlns.com/foaf/0.1/nick',
    ];

    private Logger $logger;

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

    public function getValueHash(int | float | string $val): int | float
    {
        return abs(crc32($val));
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

        $this->inferLabelProps();

        foreach ($this->labelProperties as $labelProperty) {
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

    private function inferLabelProps(): void
    {
        $this->query('DELETE FROM <label-properties>');
        $sub_q = '';
        foreach ($this->labelProperties as $p) {
            $sub_q .= ' <'.$p.'> a <http://semsol.org/ns/arc#LabelProperty> . ';
        }
        $this->query('INSERT INTO <label-properties> { '.$sub_q.' }');
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

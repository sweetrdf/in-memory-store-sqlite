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
}

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
use rdfInterface\DataFactory as iDataFactory;
use rdfInterface\Term as iTerm;
use simpleRdf\DataFactory;
use sweetrdf\InMemoryStoreSqlite\KeyValueBag;
use sweetrdf\InMemoryStoreSqlite\Log\LoggerPool;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\Parser\SPARQLPlusParser;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\AskQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\ConstructQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\DeleteQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\DescribeQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\InsertQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\SelectQueryHandler;
use sweetrdf\InMemoryStoreSqlite\StringReader;

class InMemoryStoreSqlite
{
    private bool $bulkLoadModeIsActive = true;

    private int $bulkLoadModeNextTermId = 1;

    private PDOSQLiteAdapter $db;

    private iDataFactory $dataFactory;

    private LoggerPool $loggerPool;

    private NamespaceHelper $namespaceHelper;

    private KeyValueBag $rowCache;

    private StringReader $stringReader;

    public function __construct(
        PDOSQLiteAdapter $db,
        iDataFactory $dataFactory,
        NamespaceHelper $namespaceHelper,
        LoggerPool $loggerPool,
        KeyValueBag $rowCache,
        StringReader $stringReader
    ) {
        $this->db = $db;
        $this->dataFactory = $dataFactory;
        $this->loggerPool = $loggerPool;
        $this->namespaceHelper = $namespaceHelper;
        $this->rowCache = $rowCache;
        $this->stringReader = $stringReader;
    }

    /**
     * Shortcut for people who want a ready-to-use instance.
     */
    public static function createInstance()
    {
        return new self(
            new PDOSQLiteAdapter(),
            new DataFactory(),
            new NamespaceHelper(),
            new LoggerPool(),
            new KeyValueBag(),
            new StringReader()
        );
    }

    public function getLoggerPool(): LoggerPool
    {
        return $this->loggerPool;
    }

    public function getNamespaceHelper(): NamespaceHelper
    {
        return $this->namespaceHelper;
    }

    public function getDBObject(): ?PDOSQLiteAdapter
    {
        return $this->db;
    }

    public function getDBVersion()
    {
        return $this->db->getServerVersion();
    }

    /**
     * Adds an array of raw triple-arrays to the store.
     *
     * Each triple in $triples has to look similar to:
     *
     *      [
     *          's' => 'http...',
     *          'p' => '...',
     *          'o' => '...',
     *          's_type' => 'uri',
     *          'o_type' => '...',
     *          'o_lang' => '...',
     *          'o_datatype' => '...',
     *      ]
     */
    public function addRawTriples(array $triples, string $graphIri): void
    {
        $queryHandlerLogger = $this->loggerPool->createNewLogger('QueryHandler');
        $queryHandler = new InsertQueryHandler($this, $queryHandlerLogger);

        $queryHandler->setRowCache($this->rowCache);

        if (true === $this->bulkLoadModeIsActive) {
            $queryHandler->activateBulkLoadMode($this->bulkLoadModeNextTermId);
        }

        $queryHandler->runQuery([
            'query' => [
                'construct_triples' => $triples,
                'target_graph' => $graphIri,
            ],
        ]);
    }

    /**
     * Executes a SPARQL query.
     *
     * @param string $q      SPARQL query
     * @param string $format One of: raw, instances
     */
    public function query(string $q, string $format = 'raw'): array | bool | iTerm
    {
        $errors = [];

        if (preg_match('/^dump/i', $q)) {
            $infos = ['query' => ['type' => 'dump']];
        } else {
            $parserLogger = $this->loggerPool->createNewLogger('SPARQL');
            $p = new SPARQLPlusParser($parserLogger, $this->namespaceHelper, $this->stringReader);
            $p->parse($q);
            $infos = $p->getQueryInfos();
            $errors = $parserLogger->getEntries('error');

            if (0 < \count($errors)) {
                throw new Exception('Query failed: '.json_encode($errors));
            }
        }

        $queryType = $infos['query']['type'];
        $validTypes = ['select', 'ask', 'describe', 'construct', 'load', 'insert', 'delete', 'dump'];
        if (!\in_array($queryType, $validTypes)) {
            throw new Exception('Unsupported query type "'.$queryType.'"');
        }

        $cls = match ($queryType) {
            'ask' => AskQueryHandler::class,
            'construct' => ConstructQueryHandler::class,
            'describe' => DescribeQueryHandler::class,
            'delete' => DeleteQueryHandler::class,
            'insert' => InsertQueryHandler::class,
            'select' => SelectQueryHandler::class,
        };

        if (empty($cls)) {
            throw new Exception('Inalid query $type given.');
        }

        $queryHandlerLogger = $this->loggerPool->createNewLogger('QueryHandler');
        $queryHandler = new $cls($this, $queryHandlerLogger);

        if ('insert' == $queryType) {
            $queryHandler->setRowCache($this->rowCache);

            if (true === $this->bulkLoadModeIsActive) {
                $queryHandler->activateBulkLoadMode($this->bulkLoadModeNextTermId);
            }
        } elseif ('delete' == $queryType) {
            // reset row cache, because it will not be notified of data changes
            $this->rowCache->reset();
            $this->bulkLoadModeIsActive = false;
        }

        $queryResult = $queryHandler->runQuery($infos);

        if ('insert' == $queryType && true === $this->bulkLoadModeIsActive) {
            // save latest term ID in case further insert into queries follow
            $this->bulkLoadModeNextTermId = $queryHandler->getBulkLoadModeNextTermId();
        }

        $result = null;
        if ('raw' == $format) {
            // use plain old ARC2 format which is an array of arrays
            $result = ['query_type' => $queryType, 'result' => $queryResult];
        } elseif ('instances' == $format) {
            // use rdfInstance instance(s) to represent result entries
            if (\is_array($queryResult)) {
                $variables = $queryResult['variables'];

                foreach ($queryResult['rows'] as $row) {
                    $resultEntry = [];
                    foreach ($variables as $variable) {
                        if ('uri' == $row[$variable.' type']) {
                            $resultEntry[$variable] = $this->dataFactory->namedNode($row[$variable]);
                        } elseif ('bnode' == $row[$variable.' type']) {
                            $resultEntry[$variable] = $this->dataFactory->blankNode($row[$variable]);
                        } elseif ('literal' == $row[$variable.' type']) {
                            $resultEntry[$variable] = $this->dataFactory->literal(
                                $row[$variable],
                                $row[$variable.' lang'] ?? null,
                                $row[$variable.' datatype'] ?? null
                            );
                        } else {
                            throw new Exception('Invalid type given: '.$row[$variable.' type']);
                        }
                    }
                    $result[] = $resultEntry;
                }
            } else {
                $result = $this->dataFactory->literal($queryResult);
            }
        }

        return $result;
    }
}

<?php

/**
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-2 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Benjamin Nowack
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sweetrdf\InMemoryStoreSqlite\Store;

use Exception;
use rdfInterface\BlankNodeInterface;
use rdfInterface\DataFactoryInterface;
use rdfInterface\LiteralInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\QuadIteratorInterface;
use simpleRdf\DataFactory;
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
    private PDOSQLiteAdapter $db;

    private DataFactoryInterface $dataFactory;

    private InsertQueryHandler $insertQueryHandler;

    private LoggerPool $loggerPool;

    private NamespaceHelper $namespaceHelper;

    private StringReader $stringReader;

    /**
     * @internal Don't use the constructor directly because parameters may change. Use createInstance() instead.
     */
    public function __construct(
        PDOSQLiteAdapter $db,
        DataFactoryInterface $dataFactory,
        NamespaceHelper $namespaceHelper,
        LoggerPool $loggerPool,
        StringReader $stringReader
    ) {
        $this->db = $db;
        $this->dataFactory = $dataFactory;
        $this->loggerPool = $loggerPool;
        $this->namespaceHelper = $namespaceHelper;
        $this->stringReader = $stringReader;

        $this->insertQueryHandler = new InsertQueryHandler($this, $this->loggerPool->createNewLogger('QueryHandler'));
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

    public function addQuads(iterable | QuadIteratorInterface $quads): void
    {
        $triples = [];

        foreach ($quads as $quad) {
            $graphIri = NamespaceHelper::BASE_NAMESPACE;
            if (null !== $quad->getGraph()) {
                $graphIri = $quad->getGraph()->getValue();
            }

            if (!isset($triples[$graphIri])) {
                $triples[$graphIri] = [];
            }

            $triple = [
                's' => $quad->getSubject()->getValue(),
                's_type' => $quad->getSubject() instanceof NamedNodeInterface ? 'uri' : 'bnode',
                'p' => $quad->getPredicate()->getValue(),
                'o' => $quad->getObject()->getValue(),
                'o_type' => '',
                'o_lang' => '',
                'o_datatype' => '',
            ];

            // o
            if ($quad->getObject() instanceof NamedNodeInterface) {
                $triple['o_type'] = 'uri';
            } elseif ($quad->getObject() instanceof BlankNodeInterface) {
                $triple['o_type'] = 'bnode';
            } else {
                $triple['o_type'] = 'literal';
                $triple['o_lang'] = $quad->getObject()->getLang();
                $triple['o_datatype'] = $quad->getObject()->getDatatype();
            }

            $triples[$graphIri][] = $triple;
        }

        foreach ($triples as $graphIri => $entries) {
            $this->addRawTriples($entries, $graphIri);
        }
    }

    /**
     * Adds an array of raw triple-arrays to the store.
     *
     * Each triple-array in $triples has to look similar to:
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
        $this->insertQueryHandler->runQuery([
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
    public function query(string $q, string $format = 'raw'): array | LiteralInterface
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

        if ('insert' == $queryType) {
            // reuse InsertQueryHandler because its max term ID
            // when it gets recreated everytime the max term ID start by 1 and we get:
            // Integrity constraint violation: 19 UNIQUE constraint failed: id2val.id
            $queryHandler = $this->insertQueryHandler;
        } else {
            $queryHandlerLogger = $this->loggerPool->createNewLogger('QueryHandler');
            $queryHandler = new $cls($this, $queryHandlerLogger);
        }

        $queryResult = $queryHandler->runQuery($infos);

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

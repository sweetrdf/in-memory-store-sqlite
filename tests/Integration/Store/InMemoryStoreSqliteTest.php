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

namespace Tests\Integration\Store;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use simpleRdf\DataFactory;
use sparqlClient\Connection;
use sweetrdf\InMemoryStoreSqlite\KeyValueBag;
use sweetrdf\InMemoryStoreSqlite\Log\LoggerPool;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use sweetrdf\InMemoryStoreSqlite\StringReader;
use Tests\TestCase;

class InMemoryStoreSqliteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = InMemoryStoreSqlite::createInstance();
    }

    /*
     * Tests for createInstance
     */

    public function testCreateInstance()
    {
        $expected = new InMemoryStoreSqlite(
            new PDOSQLiteAdapter(),
            new DataFactory(),
            new NamespaceHelper(),
            new LoggerPool(),
            new KeyValueBag(),
            new StringReader()
        );
        $this->assertEquals($expected, InMemoryStoreSqlite::createInstance());
    }

    /*
     * Tests for getDBVersion
     */

    /**
     * just check pattern
     */
    public function testGetDBVersion()
    {
        $pattern = '/[0-9]{1,}\.[0-9]{1,}\.[0-9]{1,}/';
        $result = preg_match($pattern, $this->subjectUnderTest->getDBVersion(), $match);
        $this->assertEquals(1, $result);
    }

    /**
     * This test checks gathering of freshly created resources.
     */
    public function testInsertSaftRegressionTest2()
    {
        $res = $this->subjectUnderTest->query('INSERT INTO <http://localhost/Saft/TestGraph/> {<http://foo/1> <http://foo/2> <http://foo/3> . }');

        $res1 = $this->subjectUnderTest->query('SELECT * FROM <http://localhost/Saft/TestGraph/> WHERE {?s ?p ?o.}');
        $this->assertEquals(1, \count($res1['result']['rows']));

        $res2 = $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}');
        $this->assertEquals(1, \count($res2['result']['rows']));

        $res2 = $this->subjectUnderTest->query('SELECT ?s ?p ?o WHERE {?s ?p ?o.}');
        $this->assertEquals(1, \count($res2['result']['rows']));
    }

    /**
     * This test checks side effects of update operations on different graphs.
     *
     * We add 1 triple to 1 and another to another graph.
     * Afterwards first graph is removed.
     * In the end second graph still should contain its triples.
     */
    public function testInsertSaftRegressionTest3()
    {
        $this->subjectUnderTest->query(
            'INSERT INTO <http://localhost/Saft/TestGraph/> {<http://localhost/Saft/TestGraph/> <http://localhost/Saft/TestGraph/> <http://localhost/Saft/TestGraph/> . }'
        );
        $this->subjectUnderTest->query(
            'INSERT INTO <http://second-graph/> {<http://second-graph/0> <http://second-graph/1> <http://second-graph/2> . }'
        );
        $this->subjectUnderTest->query(
            'DELETE FROM <http://localhost/Saft/TestGraph/>'
        );

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://second-graph/> WHERE {?s ?p ?o.}');
        $this->assertEquals(1, \count($res['result']['rows']));
    }

    public function testAddQuads()
    {
        // check data at the beginning
        $res = $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}');
        $this->assertCount(0, $res['result']['rows']);

        /*
         * add quads
         */
        $df = new DataFactory();
        $graph = 'http://graph';

        // q1
        $q1 = $df->quad(
            $df->namedNode('http://a'),
            $df->namedNode('http://b'),
            $df->namedNode('http://c'),
            $df->namedNode($graph)
        );

        // q2
        $q2 = $df->quad(
            $df->blankNode('123'),
            $df->namedNode('http://b'),
            $df->literal('foobar', 'de'),
            $df->namedNode($graph)
        );

        $quads = [$q1, $q2];

        $this->subjectUnderTest->addQuads($quads);

        // check after quads were added
        $res = $this->subjectUnderTest->query('SELECT * FROM <'.$graph.'> WHERE {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => 'http://a',
                    's type' => 'uri',
                    'p' => 'http://b',
                    'p type' => 'uri',
                    'o' => 'http://c',
                    'o type' => 'uri',
                ],
                [
                    's' => $res['result']['rows'][1]['s'], // dynamic value
                    's type' => 'bnode',
                    'p' => 'http://b',
                    'p type' => 'uri',
                    'o' => 'foobar',
                    'o type' => 'literal',
                    'o lang' => 'de',
                ],
            ],
            $res['result']['rows']
        );
    }

    /**
     * Tests compatibility with sweetrdf/sparqlClient.
     *
     * It queries a SPARQL endpoint and adds result to store.
     */
    public function testSparqlClientCompatibility()
    {
        /*
         * get data from a SPARQL endpoint
         */
        $httpClient = new Client();
        $dataFactory = new DataFactory();
        $connection = new Connection($httpClient, $dataFactory);
        $query = 'SELECT * WHERE {?s ?p ?o} limit 5';
        $url = 'https://arche-sparql.acdh-dev.oeaw.ac.at/sparql?query=';
        $query = new Request('GET', $url.rawurlencode($query));
        $statement = $connection->query($query);

        /*
         * add result to the store
         */
        $dataFactory = new DataFactory();
        $quads = [];
        foreach ($statement as $entry) {
            $quads[] = $dataFactory->quad($entry->s, $entry->p, $entry->o);
        }

        $store = InMemoryStoreSqlite::createInstance();
        $store->addQuads($quads);

        /*
         * check result
         */
        $this->assertCount(5, $store->query('SELECT * WHERE {?s ?p ?o.}')['result']['rows']);
    }
}

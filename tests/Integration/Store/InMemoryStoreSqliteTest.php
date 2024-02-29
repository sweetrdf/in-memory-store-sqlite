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

namespace Tests\Integration\Store;

use simpleRdf\DataFactory;
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
        $this->subjectUnderTest->query('INSERT INTO <http://localhost/Saft/TestGraph/> {<http://foo/1> <http://foo/2> <http://foo/3> . }');

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
}

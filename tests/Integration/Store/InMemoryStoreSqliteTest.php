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

use sweetrdf\InMemoryStoreSqlite\KeyValueBag;
use sweetrdf\InMemoryStoreSqlite\Log\LoggerPool;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
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
        $this->assertEquals(
            InMemoryStoreSqlite::createInstance(),
            new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new LoggerPool(), new KeyValueBag())
        );
    }

    /*
     * Tests for delete
     */

    public function testDelete()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
            <http://s> <http://xmlns.com/foaf/0.1/name> "label1" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}');
        $this->assertEquals(2, \count($res['result']['rows']));

        // remove graph
        $this->subjectUnderTest->delete(false, 'http://example.com/');

        $res = $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}');
        $this->assertEquals(0, \count($res['result']['rows']));
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
     * https://github.com/SaftIng/Saft/tree/master/src/Saft/Addition/ARC2
     *
     * @group linux
     */
    public function testInsertSaftRegressionTest1()
    {
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://example.com/> WHERE { ?s ?p ?o. } ');
        $this->assertEquals(0, \count($res['result']['rows']));

        $this->subjectUnderTest->insert(
            file_get_contents($this->rootPath.'/data/nt/saft-arc2-addition-regression1.nt'),
            'http://example.com/'
        );

        $res1 = $this->subjectUnderTest->query('SELECT * FROM <http://example.com/> WHERE { ?s ?p ?o. } ');
        $this->assertEquals(442, \count($res1['result']['rows']));

        $res2 = $this->subjectUnderTest->query('SELECT * WHERE { ?s ?p ?o. } ');
        $this->assertEquals(442, \count($res2['result']['rows']));
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
}

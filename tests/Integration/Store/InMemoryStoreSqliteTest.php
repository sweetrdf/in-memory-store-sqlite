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

use sweetrdf\InMemoryStoreSqlite\Logger;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

class InMemoryStoreSqliteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new Logger());
    }

    /**
     * Returns a list of all available graph URIs of the store. It can also respect access control,
     * to only returned available graphs in the current context. But that depends on the implementation
     * and can differ.
     *
     * @return array simple array of key-value-pairs, which consists of graph URIs as values
     */
    protected function getGraphs()
    {
        // collects all values which have an ID (column g) in the g2t table.
        $query = 'SELECT id2val.val AS graphUri FROM g2t LEFT JOIN id2val ON g2t.g = id2val.id GROUP BY g';

        // send SQL query
        $list = $this->subjectUnderTest->getDBObject()->fetchList($query);
        $graphs = [];

        // collect graph URI's
        foreach ($list as $row) {
            $graphs[] = $row['graphUri'];
        }

        return $graphs;
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

    // just check pattern
    public function testGetDBVersion()
    {
        $pattern = '/[0-9]{1,}\.[0-9]{1,}\.[0-9]{1,}/';
        $result = preg_match($pattern, $this->subjectUnderTest->getDBVersion(), $match);
        $this->assertEquals(1, $result);
    }

    /*
     * Tests for getSetting and setSetting
     */

    public function testGetAndSetSetting()
    {
        $this->assertEquals(0, $this->subjectUnderTest->getSetting('foo'));

        $this->subjectUnderTest->setSetting('foo', 'bar');

        $this->assertEquals('bar', $this->subjectUnderTest->getSetting('foo'));
    }

    public function testGetAndSetSettingUseDefault()
    {
        $this->assertEquals('no-entry', $this->subjectUnderTest->getSetting('not-available-'.time(), 'no-entry'));
    }

    public function testGetAndSetSettingExistingSetting()
    {
        $this->assertEquals(0, $this->subjectUnderTest->getSetting('foo'));

        $this->subjectUnderTest->setSetting('foo', 'bar');
        $this->subjectUnderTest->setSetting('foo', 'bar2'); // overrides existing setting

        $this->assertEquals('bar2', $this->subjectUnderTest->getSetting('foo'));
    }

    /*
     * Tests for getLabelProps
     */

    public function testGetLabelProps()
    {
        $this->assertEquals(
            [
                'http://www.w3.org/2000/01/rdf-schema#label',
                'http://xmlns.com/foaf/0.1/name',
                'http://purl.org/dc/elements/1.1/title',
                'http://purl.org/rss/1.0/title',
                'http://www.w3.org/2004/02/skos/core#prefLabel',
                'http://xmlns.com/foaf/0.1/nick',
            ],
            $this->subjectUnderTest->getLabelProps()
        );
    }

    /*
     * Tests for getResourceLabel
     */

    public function testGetResourceLabel()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
            <http://s> <http://xmlns.com/foaf/0.1/name> "label1" .
        }');

        $res = $this->subjectUnderTest->getResourceLabel('http://s');

        $this->assertEquals('label1', $res);
    }

    public function testGetResourceLabelNoData()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->getResourceLabel('http://s');

        $this->assertEquals('s', $res);
    }

    /*
     * Tests for getResourcePredicates
     */

    public function testGetResourcePredicates()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
            <http://s> <http://p2> "bar" .
        }');

        $res = $this->subjectUnderTest->getResourcePredicates('http://s');

        $this->assertEquals(
            [
                'http://p1' => [],
                'http://p2' => [],
            ],
            $res
        );
    }

    public function testGetResourcePredicatesMultipleGraphs()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
            <http://s> <http://p2> "bar" .
        }');

        $this->subjectUnderTest->query('INSERT INTO <http://example.com/2> {
            <http://s> <http://p3> "baz" .
            <http://s> <http://p4> "bar" .
        }');

        $res = $this->subjectUnderTest->getResourcePredicates('http://s');

        $this->assertEquals(
            [
                'http://p1' => [],
                'http://p2' => [],
                'http://p3' => [],
                'http://p4' => [],
            ],
            $res
        );
    }

    /*
     * Tests for getPredicateRange
     */

    public function testGetPredicateRange()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://p1> <http://www.w3.org/2000/01/rdf-schema#range> <http://foobar> .
        }');

        $res = $this->subjectUnderTest->getPredicateRange('http://p1');

        $this->assertEquals('http://foobar', $res);
    }

    public function testGetPredicateRangeNotFound()
    {
        $res = $this->subjectUnderTest->getPredicateRange('http://not-available');

        $this->assertEquals('', $res);
    }

    /*
     * Tests for getIDValue
     */

    public function testGetIDValue()
    {
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://p1> <http://www.w3.org/2000/01/rdf-schema#range> <http://foobar> .
        }');

        $res = $this->subjectUnderTest->getIDValue(1);

        $this->assertEquals('http://example.com/', $res);
    }

    public function testGetIDValueNoData()
    {
        $res = $this->subjectUnderTest->getIDValue(1);

        $this->assertEquals(0, $res);
    }

    /**
     * Saft frameworks ARC2 addition fails to run with ARC2 2.4.
     *
     * https://github.com/SaftIng/Saft/tree/master/src/Saft/Addition/ARC2
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
     * Saft frameworks ARC2 addition fails to run with ARC2 2.4.
     *
     * https://github.com/SaftIng/Saft/tree/master/src/Saft/Addition/ARC2
     *
     * This tests checks gathering of freshly created resources.
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
     * Saft frameworks ARC2 addition fails to run with ARC2 2.4.
     *
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

    public function testMultipleInsertQuerysInDifferentGraphs()
    {
        $this->markTestSkipped(
            'Adding the same triple into two graphs does not work.'
            .\PHP_EOL.'Bug report: https://github.com/semsol/arc2/issues/114'
        );

        /*
         * the following checks will not go through because of the bug in #114
         *

        $this->subjectUnderTest->query('INSERT INTO <http://graph1/> {<http://foo/1> <http://foo/2> <http://foo/3> . }');
        $this->subjectUnderTest->query('INSERT INTO <http://graph2/> {<http://foo/4> <http://foo/5> <http://foo/6> . }');
        $this->subjectUnderTest->query('INSERT INTO <http://graph2/> {<http://foo/a> <http://foo/b> <http://foo/c> . }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph1/> WHERE {?s ?p ?o.}');
        $this->assertEquals(1, \count($res['result']['rows']));

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph2/> WHERE {?s ?p ?o.}');
        $this->assertEquals(2, \count($res['result']['rows']));

        $res = $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}');
        $this->assertEquals(3, \count($res['result']['rows']));
        */
    }

    /*
     * Tests for reset
     */

    public function testResetKeepSettings()
    {
        $this->subjectUnderTest->setSetting('foo', 'bar');
        $this->assertEquals(1, $this->subjectUnderTest->hasSetting('foo'));

        $this->subjectUnderTest->reset(1);

        $this->assertEquals(1, $this->subjectUnderTest->hasSetting('foo'));
    }
}

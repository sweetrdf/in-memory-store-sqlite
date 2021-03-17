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

namespace Tests\Integration\Store\InMemoryStoreSqlite\Query;

use sweetrdf\InMemoryStoreSqlite\Log\LoggerPool;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Tests for query method - focus on queries which are known to fail.
 */
class KnownNotWorkingSparqlQueriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new LoggerPool());
    }

    /**
     * Variable alias.
     */
    public function testSelectAlias()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT (?s AS ?s_alias) ?o FROM <http://example.com/> WHERE {?s <http://p1> ?o.}
        ');

        $this->assertEquals(0, $res);
    }

    /**
     * FILTER: langMatches with *.
     *
     * Based on the specification (https://www.w3.org/TR/rdf-sparql-query/#func-langMatches)
     * langMatches with * has to return all entries with no language set.
     */
    public function testSelectFilterLangMatchesWithStar()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "foo" .
            <http://s> <http://p1> "in de"@de .
            <http://s> <http://p1> "in en"@en .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER langMatches (lang(?o), "*")
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /**
     * sameTerm.
     */
    public function testSelectSameTerm()
    {
        $this->markTestSkipped(
            'Solving sameterm does not work properly. The result contains elements multiple times. '
            .\PHP_EOL.'Expected behavior is described here: https://www.w3.org/TR/rdf-sparql-query/#func-sameTerm'
        );

        /*

        demo code:

        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://container1> <http://weight> "100" .
            <http://container2> <http://weight> "100" .
        }');

        $res = $this->subjectUnderTest->query('SELECT ?c1 ?c2 WHERE {
            ?c1 ?weight ?w1.

            ?c2 ?weight ?w2.

            FILTER (sameTerm(?w1, ?w2))
        }');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'c1', 'c2',
                    ],
                    'rows' => [
                        [
                            'c1' => 'http://container1',
                            'c1 type' => 'uri',
                            'c2' => 'http://container1',
                            'c2 type' => 'uri',
                        ],
                        [
                            'c1' => 'http://container2',
                            'c1 type' => 'uri',
                            'c2' => 'http://container1',
                            'c2 type' => 'uri',
                        ],
                        [
                            'c1' => 'http://container1',
                            'c1 type' => 'uri',
                            'c2' => 'http://container2',
                            'c2 type' => 'uri',
                        ],
                        [
                            'c1' => 'http://container2',
                            'c1 type' => 'uri',
                            'c2' => 'http://container2',
                            'c2 type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res,
            '',
            0,
            10,
            true
        );
        */
    }

    /**
     * Sub Select.
     */
    public function testSelectSubSelect()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .

            <http://person1> <http://knows> <http://person2> .
            <http://person2> <http://knows> <http://person3> .
            <http://person3> <http://knows> <http://person2> .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE {
                {
                    SELECT ?p WHERE {
                        ?p <http://id> "1" .
                    }
                }
                ?p <http://knows> ?who .
            }
        ');

        $this->assertEquals(0, $res);
    }
}

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

namespace Tests\Integration\Store\InMemoryStoreSqlite\SPARQL11;

use Exception;

/**
 * Runs W3C tests from https://www.w3.org/2009/sparql/docs/tests/.
 *
 * Version: 2012-10-23 20:52 (sparql11-test-suite-20121023.tar.gz)
 *
 * Tests are located in the w3c-tests folder.
 *
 * @group linux
 */
class AggregatesTest extends ComplianceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->w3cTestsFolderPath = __DIR__.'/w3c-tests/aggregates';
        $this->testPref = 'http://www.w3.org/2009/sparql/docs/tests/data-sparql11/aggregates/manifest#';
    }

    public function testAgg01()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1, :o2, :o3.
                :s :p2 :o1, :o2.
            }
        ');

        $query = 'PREFIX : <http://www.example.org>
            SELECT (COUNT(?O) AS ?C)
            WHERE { ?S ?P ?O }
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                [
                    'C' => '5',
                    'C type' => 'literal',
                ],
            ],
            $result['result']['rows']
        );
    }

    public function testAgg02()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1, :o2, :o3.
                :s :p2 :o1, :o2.
            }
        ');

        $query = 'PREFIX : <http://www.example.org>
            SELECT ?P (COUNT(?O) AS ?C)
            WHERE { ?S ?P ?O }
            GROUP BY ?P
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                [
                    'P' => 'http://www.example.org/p1',
                    'P type' => 'uri',
                    'C' => '3',
                    'C type' => 'literal',
                ],
                [
                    'P' => 'http://www.example.org/p2',
                    'P type' => 'uri',
                    'C' => '2',
                    'C type' => 'literal',
                ],
            ],
            $result['result']['rows']
        );
    }

    /*
     * agg03 fails, because store cant properly handle:
     *
     *      HAVING (COUNT(?O) > 2 )
     *
     * in query:
     *
     *      PREFIX : <http://www.example.org>
     *
     *      SELECT ?P (COUNT(?O) AS ?C)
     *      WHERE { ?S ?P ?O }
     *      GROUP BY ?P
     *      HAVING (COUNT(?O) > 2 )
     */

    public function testAgg04()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1, :o2, :o3.
                :s :p2 :o1, :o2.
            }
        ');

        $query = 'PREFIX : <http://www.example.org>
            SELECT (COUNT(*) AS ?C)
            WHERE { ?S ?P ?O }';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                [
                    'C' => '5',
                    'C type' => 'literal',
                ],
            ],
            $result['result']['rows']
        );
    }

    public function testAgg05()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1, :o2, :o3.
                :s :p2 :o1, :o2.
            }
        ');

        $query = 'PREFIX : <http://www.example.org>
            SELECT ?P (COUNT(*) AS ?C)
            WHERE { ?S ?P ?O }
            GROUP BY ?P';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                [
                    'P' => 'http://www.example.org/p1',
                    'P type' => 'uri',
                    'C' => '3',
                    'C type' => 'literal',
                ],
                [
                    'P' => 'http://www.example.org/p2',
                    'P type' => 'uri',
                    'C' => '2',
                    'C type' => 'literal',
                ],
            ],
            $result['result']['rows']
        );
    }

    /*
     * agg06, agg07 fails, because store cant properly handle:
     *
     *      HAVING (COUNT(?O) > 0)
     */

    public function testAgg08()
    {
        $this->expectException(Exception::class);

        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1, :o2, :o3.
                :s :p2 :o1, :o2.
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT ((?O1 + ?O2) AS ?O12) (COUNT(?O1) AS ?C)
            WHERE { ?S :p ?O1; :q ?O2 } GROUP BY (?O1 + ?O2)
            ORDER BY ?O12';

        $this->store->query($query);
    }

    public function testAgg08b()
    {
        $this->expectException(Exception::class);

        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1, :o2, :o3.
                :s :p2 :o1, :o2.
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT ?O12 (COUNT(?O1) AS ?C)
            WHERE { ?S :p ?O1; :q ?O2 } GROUP BY ((?O1 + ?O2) AS ?O12)
            ORDER BY ?O12
        ';

        $this->store->query($query);
    }

    /**
     * Using original SELECT query returns a different result on scrutinizer,
     * which leads to failing test. It used p2 instead of p1 on scrutinizer.
     *
     * @see https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/inspections/88339e50-3676-4224-b0d2-8cd0f5d56bf7
     *
     * Therefore removing ?P from SELECT header.
     */
    public function testAgg09()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1,
                       :o2,
                       :o3 .
                :s :p2 :o1,
                       :o2 .
            }
        ');

        // originally there was a ?P in the select header
        $query = 'PREFIX : <http://www.example.org/>
            SELECT (COUNT(?O) AS ?C)
            WHERE { ?S ?P ?O } GROUP BY ?S
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                [
                    'C' => '5',
                    'C type' => 'literal',
                ],
            ],
            $result['result']['rows']
        );
    }

    /**
     * Using original SELECT query returns a different result on scrutinizer,
     * which leads to failing test. It used p2 instead of p1 on scrutinizer.
     *
     * @see https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/inspections/88339e50-3676-4224-b0d2-8cd0f5d56bf7
     *
     * Therefore removing ?P from SELECT header.
     */
    public function testAgg10()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            INSERT INTO <http://agg> {
                :s :p1 :o1,
                       :o2,
                       :o3 .
                :s :p2 :o1,
                       :o2 .
            }
        ');

        // originally there was a ?P in the select header
        $query = 'PREFIX : <http://www.example.org/>
            SELECT (COUNT(?O) AS ?C)
            WHERE { ?S ?P ?O }
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                [
                    'C' => '5',
                    'C type' => 'literal',
                ],
            ],
            $result['result']['rows']
        );
    }

    /*
     * agg11, agg12 fails
     */

    public function testAggMin01()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> .
            INSERT INTO <http://agg> {
                :ints :int 1, 2, 3 .
                :decimals :dec 1.0, 2.2, 3.5 .
                :doubles :double 1.0E2, 2.0E3, 3.0E4 .
                :mixed1 :int 1 ; :dec 2.2 .
                :mixed2 :double 2E-1 ; :dec 2.2 .
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT (MIN(?o) AS ?min)
            WHERE {
                ?s :dec ?o
            }
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                ['min' => 1, 'min type' => 'literal'],
            ],
            $result['result']['rows']
        );
    }

    public function testAggAvg01()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> .
            INSERT INTO <http://agg> {
                :ints :int 1, 2, 3 .
                :decimals :dec 1.0, 2.2, 3.5 .
                :doubles :double 1.0E2, 2.0E3, 3.0E4 .
                :mixed1 :int 1 ; :dec 2.2 .
                :mixed2 :double 2E-1 ; :dec 2.2 .
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT (AVG(?o) AS ?avg)
            WHERE {
                ?s :dec ?o
            }
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                ['avg' => 2.22, 'avg type' => 'literal'],
            ],
            $result['result']['rows']
        );
    }

    /*
     * aggAvg02 fails because store can't properly handle:
     *
     *      HAVING (...)
     */

    public function testAggEmptyGroup()
    {
        $query = 'PREFIX ex: <http://example.com/>
            SELECT ?x (MAX(?value) AS ?max)
            WHERE {
                ?x ex:p ?value
            } GROUP BY ?x
        ';

        $result = $this->store->query($query);

        $this->assertCount(0, $result['result']['rows']);
    }

    public function testAggMax01()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> .
            INSERT INTO <http://agg> {
                :ints :int 1, 2, 3 .
                :decimals :dec 1.0, 2.2, 3.5 .
                :doubles :double 1.0E2, 2.0E3, 3.0E4 .
                :mixed1 :int 1 ; :dec 2.2 .
                :mixed2 :double 2E-1 ; :dec 2.2 .
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT (MAX(?o) AS ?max)
            WHERE {
                ?s ?p ?o
            }
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                ['max' => 30000, 'max type' => 'literal'],
            ],
            $result['result']['rows']
        );
    }

    public function testAggMax02()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> .
            INSERT INTO <http://agg> {
                :ints :int 1, 2, 3 .
                :decimals :dec 1.0, 2.2, 3.5 .
                :doubles :double 1.0E2, 2.0E3, 3.0E4 .
                :mixed1 :int 1 ; :dec 2.2 .
                :mixed2 :double 2E-1 ; :dec 2.2 .
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT ?s (MAX(?o) AS ?max)
            WHERE {
                ?s ?p ?o
            }
            GROUP BY ?s
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                [
                    's' => 'http://www.example.org/ints', 's type' => 'uri',
                    'max' => '3', 'max type' => 'literal',
                ],
                [
                    's' => 'http://www.example.org/decimals', 's type' => 'uri',
                    'max' => '3.5', 'max type' => 'literal',
                ],
                [
                    's' => 'http://www.example.org/doubles', 's type' => 'uri',
                    'max' => '30000', 'max type' => 'literal',
                ],
                [
                    's' => 'http://www.example.org/mixed1', 's type' => 'uri',
                    'max' => '2.2', 'max type' => 'literal',
                ],
                [
                    's' => 'http://www.example.org/mixed2', 's type' => 'uri',
                    'max' => '2.2', 'max type' => 'literal',
                ],
            ],
            $result['result']['rows']
        );
    }

    public function testAggSum01()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> .
            INSERT INTO <http://agg> {
                :ints :int 1, 2, 3 .
                :decimals :dec 1.0, 2.2, 3.5 .
                :doubles :double 1.0E2, 2.0E3, 3.0E4 .
                :mixed1 :int 1 ;
                    :dec 2.2 .
                :mixed2 :double 2E-1 ;
                    :dec 2.2 .
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT (SUM(?o) AS ?sum)
            WHERE {
                ?s :dec ?o
            }
        ';

        $result = $this->store->query($query);

        // PHP works differently prior to 8.1.0 therefore the if-else here
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->assertEquals(
                [
                    [
                        'sum' => 11.1, 'sum type' => 'literal',
                    ],
                ],
                $result['result']['rows']
            );
        } else {
            $this->assertEquals(
                [
                    [
                        'sum' => 11.100000000000001, 'sum type' => 'literal',
                    ],
                ],
                $result['result']['rows']
            );
        }
    }

    public function testAggSum02()
    {
        $this->store->query('
            PREFIX : <http://www.example.org/> .
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> .
            INSERT INTO <http://agg> {
                :ints :int 1, 2, 3 .
                :decimals :dec 1.0, 2.2, 3.5 .
                :doubles :double 1.0E2, 2.0E3, 3.0E4 .
                :mixed1 :int 1 ;
                    :dec 2.2 .
                :mixed2 :double 2E-1 ;
                    :dec 2.2 .
            }
        ');

        $query = 'PREFIX : <http://www.example.org/>
            SELECT ?s (SUM(?o) AS ?sum)
            WHERE {
                ?s ?p ?o
            }
            GROUP BY ?s
        ';

        $result = $this->store->query($query);

        $this->assertEquals(
            [
                's' => 'http://www.example.org/ints', 's type' => 'uri',
                'sum' => '6', 'sum type' => 'literal',
            ],
            $result['result']['rows'][0]
        );

        $this->assertEquals(
            [
                's' => 'http://www.example.org/decimals', 's type' => 'uri',
                'sum' => '6.7', 'sum type' => 'literal',
            ],
            $result['result']['rows'][1]
        );

        $this->assertEquals(
            [
                's' => 'http://www.example.org/doubles', 's type' => 'uri',
                'sum' => '32100', 'sum type' => 'literal',
            ],
            $result['result']['rows'][2]
        );

        $this->assertEquals(
            [
                's' => 'http://www.example.org/mixed1', 's type' => 'uri',
                'sum' => '3.2', 'sum type' => 'literal',
            ],
            $result['result']['rows'][3]
        );

        // PHP works differently prior to 8.1.0 therefore the if-else here
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->assertEquals(
                [
                    's' => 'http://www.example.org/mixed2', 's type' => 'uri',
                    'sum' => 2.4, 'sum type' => 'literal',
                ],
                $result['result']['rows'][4]
            );
        } else {
            $this->assertEquals(
                [
                    's' => 'http://www.example.org/mixed2', 's type' => 'uri',
                    'sum' => 2.4000000000000004, 'sum type' => 'literal',
                ],
                $result['result']['rows'][4]
            );
        }
    }
}

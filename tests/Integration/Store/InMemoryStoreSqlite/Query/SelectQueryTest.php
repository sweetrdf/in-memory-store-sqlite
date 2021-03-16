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

use sweetrdf\InMemoryStoreSqlite\Logger;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Tests for query method - focus on SELECT queries.
 */
class SelectQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new Logger());
    }

    public function testSelectDefaultGraph()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * WHERE {<http://s> <http://p1> ?o.}');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'o',
                    ],
                    'rows' => [
                        [
                            'o' => 'baz',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    public function testSelectGraphSpecified()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://example.com/> WHERE {<http://s> <http://p1> ?o.}');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'o',
                    ],
                    'rows' => [
                        [
                            'o' => 'baz',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // simulate a LEFT JOIN using OPTIONAL
    public function testSelectLeftJoinUsingOptional()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://a> <http://p1> <http://b> .
            <http://a> <http://p1> <http://c> .

            <http://b> <http://p1> <http://d> .
            <http://b> <http://p1> <http://e> .

            <http://c> <http://p1> <http://f> .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE {
                ?s <http://p1> ?o .
                OPTIONAL {
                    ?o <http://p1> ?o2 .
                }
            }
        ');
        $this->assertEquals(
            [
                [
                    's' => 'http://a',
                    's type' => 'uri',
                    'o' => 'http://b',
                    'o type' => 'uri',
                ],
                [
                    's' => 'http://a',
                    's type' => 'uri',
                    'o' => 'http://c',
                    'o type' => 'uri',
                ],
                [
                    's' => 'http://b',
                    's type' => 'uri',
                    'o' => 'http://d',
                    'o type' => 'uri',
                ],
                [
                    's' => 'http://b',
                    's type' => 'uri',
                    'o' => 'http://e',
                    'o type' => 'uri',
                ],
                [
                    's' => 'http://c',
                    's type' => 'uri',
                    'o' => 'http://f',
                    'o type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    /*
     * OPTIONAL, artifical query to extend coverage for store code.
     * (ARC2_StoreSelectQueryHandler::sameOptional)
     */
    public function testSelectOptional()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s1> <http://p1> <http://s2> .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE {
                ?s <http://p1> ?o .
                OPTIONAL {
                    ?o <http://p1> ?o2 .
                }
                OPTIONAL {
                    ?o <http://p1> ?o2 .
                }
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o', 'o2',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s1',
                            's type' => 'uri',
                            'o' => 'http://s2',
                            'o type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    public function testSelectNoWhereClause()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://example.com/> {<http://s> <http://p1> ?o.}');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'o',
                    ],
                    'rows' => [
                        [
                            'o' => 'baz',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /*
     * FILTER
     */

    // bound: is variable set?
    public function testSelectFilterBoundNotBounding()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "foo" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p2> ?o .
                FILTER (bound(?o))
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

    // bound: is variable set?
    public function testSelectFilterBoundVariableBounded()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "foo" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (bound(?o))
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'foo',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // datatype
    public function testSelectFilterDatatype()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> 3 .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (datatype(?o) = xsd:integer)
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => '3',
                            'o type' => 'literal',
                            'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // isBlank
    public function testSelectFilterIsBlankFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> _:foo .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isBlank(?o))
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => $res['result']['rows'][0]['o'],
                            'o type' => 'bnode',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // isBlank
    public function testSelectFilterIsBlankNotFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> <http://foo> .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isBlank(?o))
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

    // isIri
    public function testSelectFilterIsIriFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> <urn:id> .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isIri(?o))
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'urn:id',
                            'o type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // isIri
    public function testSelectFilterIsIriNotFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "foo" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isIri(?o))
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

    // isLiteral
    public function testSelectFilterIsLiteralFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "foo" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isLiteral(?o))
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'foo',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // isLiteral
    public function testSelectFilterIsLiteralNotFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> <http://foo> .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isLiteral(?o))
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

    // isUri
    public function testSelectFilterIsUriFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> <urn:id> .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isUri(?o))
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'urn:id',
                            'o type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // isUri
    public function testSelectFilterIsUriNotFound()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "foo" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER (isUri(?o))
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

    // lang: test behavior when using a language
    public function testSelectFilterLang()
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
                FILTER (lang(?o) = "en")
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'in en',
                            'o type' => 'literal',
                            'o lang' => 'en',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // langMatches
    public function testSelectFilterLangMatches()
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
                FILTER langMatches (lang(?o), "en")
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'in en',
                            'o type' => 'literal',
                            'o lang' => 'en',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // regex
    public function testSelectFilterRegex()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "Alice".
            <http://s2> <http://p1> "Bob" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER regex (?o, "^Ali")
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'Alice',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // regex
    public function testSelectFilterRegexWithModifier()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "Alice".
            <http://s2> <http://p1> "Bob" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT ?s ?o WHERE {
                ?s <http://p1> ?o .
                FILTER regex (?o, "^ali", "i")
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'Alice',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // str
    public function testSelectFilterStr()
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
                FILTER (str(?o) = "in en")
            }
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'o' => 'in en',
                            'o type' => 'literal',
                            'o lang' => 'en',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // str
    public function testSelectFilterStrNotFound()
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
                FILTER (str(?o) = "in it")
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

    // >
    public function testSelectFilterRelationalGreaterThan()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://container1> <http://weight> "150" .
            <http://container2> <http://weight> "50" .
        }');

        $res = $this->subjectUnderTest->query('SELECT ?c WHERE {
            ?c <http://weight> ?w .

            FILTER (?w > 100)
        }');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'c',
                    ],
                    'rows' => [
                        [
                            'c' => 'http://container1',
                            'c type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // <
    public function testSelectFilterRelationalSmallerThan()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://container1> <http://weight> "150" .
            <http://container2> <http://weight> "50" .
        }');

        $res = $this->subjectUnderTest->query('SELECT ?c WHERE {
            ?c <http://weight> ?w .

            FILTER (?w < 100)
        }');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'c',
                    ],
                    'rows' => [
                        [
                            'c' => 'http://container2',
                            'c type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // <
    public function testSelectFilterRelationalSmallerThan2()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://container1> <http://weight> "150" .
            <http://container2> <http://weight> "50" .
        }');

        $res = $this->subjectUnderTest->query('SELECT ?c WHERE {
            ?c <http://weight> ?w .

            FILTER (?w < 100 && ?w > 10)
        }');
        $this->assertEquals(
            [
                [
                    'c' => 'http://container2',
                    'c type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    // =
    public function testSelectFilterRelationalEqual()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://container1> <http://weight> "150" .
            <http://container2> <http://weight> "50" .
        }');

        $res = $this->subjectUnderTest->query('SELECT ?c WHERE {
            ?c <http://weight> ?w .

            FILTER (?w = 150)
        }');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'c',
                    ],
                    'rows' => [
                        [
                            'c' => 'http://container1',
                            'c type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    // !=
    public function testSelectFilterRelationalNotEqual()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://container1> <http://weight> "150" .
            <http://container2> <http://weight> "50" .
        }');

        $res = $this->subjectUnderTest->query('SELECT ?c WHERE {
            ?c <http://weight> ?w .

            FILTER (?w != 150)
        }');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'c',
                    ],
                    'rows' => [
                        [
                            'c' => 'http://container2',
                            'c type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /*
     * SELECT COUNT
     */

    public function testSelectCount()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://name> "baz" .
            <http://person2> <http://name> "baz" .
            <http://person3> <http://name> "baz" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT COUNT(?s) AS ?count WHERE {
                ?s <http://name> "baz" .
            }
            ORDER BY DESC(?count)
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'count',
                    ],
                    'rows' => [
                        [
                            'count' => '3',
                            'count type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /*
     * GROUP BY
     */

    public function testSelectGroupBy()
    {
        $query = 'SELECT ?who COUNT(?person) as ?persons WHERE {
                ?who <http://knows> ?person .
            }
            GROUP BY ?who
        ';

        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://knows> <http://person2>, <http://person3> .
            <http://person2> <http://knows> <http://person3> .
        }');

        $res = $this->subjectUnderTest->query($query);
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'who',
                        'persons',
                    ],
                    'rows' => [
                        [
                            'who' => 'http://person1',
                            'who type' => 'uri',
                            'persons' => '2',
                            'persons type' => 'literal',
                        ],
                        [
                            'who' => 'http://person2',
                            'who type' => 'uri',
                            'persons' => '1',
                            'persons type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /*
     * OFFSET and LIMIT
     */

    public function testSelectOffset()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE { ?s ?p ?o . }
            OFFSET 1
        ');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'p', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://person3',
                            's type' => 'uri',
                            'p' => 'http://id',
                            'p type' => 'uri',
                            'o' => '3',
                            'o type' => 'literal',
                        ],
                        [
                            's' => 'http://person2',
                            's type' => 'uri',
                            'p' => 'http://id',
                            'p type' => 'uri',
                            'o' => '2',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    public function testSelectOffsetLimit()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE { ?s ?p ?o . }
            OFFSET 1 LIMIT 2
        ');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'p', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://person3',
                            's type' => 'uri',
                            'p' => 'http://id',
                            'p type' => 'uri',
                            'o' => '3',
                            'o type' => 'literal',
                        ],
                        [
                            's' => 'http://person2',
                            's type' => 'uri',
                            'p' => 'http://id',
                            'p type' => 'uri',
                            'o' => '2',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    public function testSelectLimit()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE { ?s ?p ?o . }
            LIMIT 2
        ');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'p', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://person1',
                            's type' => 'uri',
                            'p' => 'http://id',
                            'p type' => 'uri',
                            'o' => '1',
                            'o type' => 'literal',
                        ],
                        [
                            's' => 'http://person3',
                            's type' => 'uri',
                            'p' => 'http://id',
                            'p type' => 'uri',
                            'o' => '3',
                            'o type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /*
     * ORDER BY
     */

    public function testSelectOrderByAsc()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE {
                ?s <http://id> ?id .
            }
            ORDER BY ASC(?id)
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's',
                        'id',
                    ],
                    'rows' => [
                        [
                            's' => 'http://person1',
                            's type' => 'uri',
                            'id' => '1',
                            'id type' => 'literal',
                        ],
                        [
                            's' => 'http://person2',
                            's type' => 'uri',
                            'id' => '2',
                            'id type' => 'literal',
                        ],
                        [
                            's' => 'http://person3',
                            's type' => 'uri',
                            'id' => '3',
                            'id type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    public function testSelectOrderByDesc()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE {
                ?s <http://id> ?id .
            }
            ORDER BY DESC(?id)
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's',
                        'id',
                    ],
                    'rows' => [
                        [
                            's' => 'http://person3',
                            's type' => 'uri',
                            'id' => '3',
                            'id type' => 'literal',
                        ],
                        [
                            's' => 'http://person2',
                            's type' => 'uri',
                            'id' => '2',
                            'id type' => 'literal',
                        ],
                        [
                            's' => 'http://person1',
                            's type' => 'uri',
                            'id' => '1',
                            'id type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    public function testSelectOrderByWithoutContent()
    {
        $res = $this->subjectUnderTest->query('
            SELECT * WHERE {
                ?s <http://id> ?id .
            }
            ORDER BY
        ');

        // query false, therefore 0 as result
        $this->assertEquals(0, $res);
    }

    /*
     * PREFIX
     */

    public function testSelectPrefix()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://example.com/kennt> <http://person2> .
        }');

        $res = $this->subjectUnderTest->query('
            PREFIX ex: <http://example.com/>
            SELECT * WHERE {
                ?s ex:kennt ?o
            }
        ');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's', 'o',
                    ],
                    'rows' => [
                        [
                            's' => 'http://person1',
                            's type' => 'uri',
                            'o' => 'http://person2',
                            'o type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /*
     * UNION
     */

    public function testSelectUnion()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * WHERE {
                {
                    ?p <http://id> "1" .
                } UNION {
                    ?p <http://id> "3" .
                }
            }
        ');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'p',
                    ],
                    'rows' => [
                        [
                            'p' => 'http://person1',
                            'p type' => 'uri',
                        ],
                        [
                            'p' => 'http://person3',
                            'p type' => 'uri',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    /*
     * Tests using certain queries with SELECT FROM WHERE and not just SELECT WHERE
     */

    public function testSelectOrderByAscWithFromClause()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://person1> <http://id> "1" .
            <http://person3> <http://id> "3" .
            <http://person2> <http://id> "2" .
        }');

        $res = $this->subjectUnderTest->query('
            SELECT * FROM <http://example.com/> WHERE {
                ?s <http://id> ?id .
            }
            ORDER BY ASC(?id)
        ');
        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        's',
                        'id',
                    ],
                    'rows' => [
                        [
                            's' => 'http://person1',
                            's type' => 'uri',
                            'id' => '1',
                            'id type' => 'literal',
                        ],
                        [
                            's' => 'http://person2',
                            's type' => 'uri',
                            'id' => '2',
                            'id type' => 'literal',
                        ],
                        [
                            's' => 'http://person3',
                            's type' => 'uri',
                            'id' => '3',
                            'id type' => 'literal',
                        ],
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }
}

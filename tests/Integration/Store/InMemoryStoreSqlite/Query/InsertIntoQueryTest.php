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

use Exception;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Tests for query method - focus on INSERT INTO queries.
 */
class InsertIntoQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = InMemoryStoreSqlite::createInstance();
    }

    public function testInsertInto()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');
        $this->assertEquals(1, \count($res['result']['rows']));
    }

    public function testInsertIntoUriTriple()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex> { <http://s> <http://p> <http://o> .}');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex> {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://p',
                    'p type' => 'uri',
                    'o' => 'http://o',
                    'o type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoShortenedUri()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex> { <#make> <#me> <#happy> .}');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex> {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => NamespaceHelper::BASE_NAMESPACE.'#make',
                    's type' => 'uri',
                    'p' => NamespaceHelper::BASE_NAMESPACE.'#me',
                    'p type' => 'uri',
                    'o' => NamespaceHelper::BASE_NAMESPACE.'#happy',
                    'o type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoPrefixedUri()
    {
        // test data
        $query = '
            PREFIX ex: <http://ex/>
            INSERT INTO <http://ex> { <http://s> rdf:type ex:Person .}
        ';
        $this->subjectUnderTest->query($query);

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex> {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
                    'p type' => 'uri',
                    'o' => 'http://ex/Person',
                    'o type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoNumbers()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex> {
            <http://s> <http://foo> 1 .
            <http://s> <http://foo> 2.0 .
            <http://s> <http://foo> "3" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex> {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://foo',
                    'p type' => 'uri',
                    'o' => '1',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://foo',
                    'p type' => 'uri',
                    'o' => '2.0',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#decimal',
                ],
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://foo',
                    'p type' => 'uri',
                    'o' => '3',
                    'o type' => 'literal',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoObjectWithDatatype()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex> {
            <http://s> <http://foo> "4"^^xsd:integer .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex> {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://foo',
                    'p type' => 'uri',
                    'o' => '4',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoObjectWithLanguage()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex> {
            <http://s> <http://foo> "5"@en .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex> {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://foo',
                    'p type' => 'uri',
                    'o' => '5',
                    'o type' => 'literal',
                    'o lang' => 'en',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoBlankNode1()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex> {
            _:foo <http://foo> "6" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex> {?s ?p ?o.}');
        $this->assertEquals(
            [
                [
                    's' => $res['result']['rows'][0]['s'], // blank node ID is dynamic
                    's type' => 'bnode',
                    'p' => 'http://foo',
                    'p type' => 'uri',
                    'o' => '6',
                    'o type' => 'literal',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoBlankNode2()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {
            <http://s> <http://p1> [
                <http://foo> <http://bar>
            ] .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');

        // because bnode ID is random, we check only its structure
        $this->assertTrue(isset($res['result']['rows'][0]));
        $this->assertEquals(1, preg_match('/_:[a-z0-9]+_[a-z0-9]+/', $res['result']['rows'][0]['o']));

        $this->assertEquals(
            [
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://p1',
                    'p type' => 'uri',
                    'o' => $res['result']['rows'][0]['o'],
                    'o type' => 'bnode',
                ],
                [
                    's' => $res['result']['rows'][0]['o'],
                    's type' => 'bnode',
                    'p' => 'http://foo',
                    'p type' => 'uri',
                    'o' => 'http://bar',
                    'o type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoBlankNode3()
    {
        // test data
        $this->subjectUnderTest->query('
            PREFIX ex: <http://ex/>
            INSERT INTO <http://ex/> {
                ex:3 ex:action [
                    ex:query  <agg-avg-01.rq> ;
                    ex:data   <agg-numeric.ttl>
                ] .
            }
        ');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');

        $this->assertEquals(
            [
                [
                    's' => 'http://ex/3',
                    's type' => 'uri',
                    'p' => 'http://ex/action',
                    'p type' => 'uri',
                    'o' => $res['result']['rows'][0]['o'],
                    'o type' => 'bnode',
                ],
                [
                    's' => $res['result']['rows'][0]['o'],
                    's type' => 'bnode',
                    'p' => 'http://ex/query',
                    'p type' => 'uri',
                    'o' => NamespaceHelper::BASE_NAMESPACE.'agg-avg-01.rq',
                    'o type' => 'uri',
                ],
                [
                    's' => $res['result']['rows'][0]['o'],
                    's type' => 'bnode',
                    'p' => 'http://ex/data',
                    'p type' => 'uri',
                    'o' => NamespaceHelper::BASE_NAMESPACE.'agg-numeric.ttl',
                    'o type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoDate()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {
            <http://s> <http://p1> "2009-05-28T18:03:38+09:00" .
            <http://s> <http://p1> "2009-05-28T18:03:38+09:00GMT" .
            <http://s> <http://p1> "21 August 2007" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => ['s', 'p', 'o'],
                    'rows' => [
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'p' => 'http://p1',
                            'p type' => 'uri',
                            'o' => '2009-05-28T18:03:38+09:00',
                            'o type' => 'literal',
                        ],
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'p' => 'http://p1',
                            'p type' => 'uri',
                            'o' => '2009-05-28T18:03:38+09:00GMT',
                            'o type' => 'literal',
                        ],
                        [
                            's' => 'http://s',
                            's type' => 'uri',
                            'p' => 'http://p1',
                            'p type' => 'uri',
                            'o' => '21 August 2007',
                            'o type' => 'literal',
                        ],
                    ],
                ],
            ],
            $res
        );
    }

    public function testInsertIntoList()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {
            <http://s> <http://p1> 1, 2, 3 .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');

        $this->assertEquals(
            [
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://p1',
                    'p type' => 'uri',
                    'o' => '1',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://p1',
                    'p type' => 'uri',
                    'o' => '2',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
                [
                    's' => 'http://s',
                    's type' => 'uri',
                    'p' => 'http://p1',
                    'p type' => 'uri',
                    'o' => '3',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
            ],
            $res['result']['rows']
        );
    }

    /**
     * Demonstrates that store can't save long values.
     */
    public function testInsertIntoLongValue()
    {
        // create long URI (ca. 250 chars)
        $longURI = 'http://'.hash('sha512', 'long')
            .hash('sha512', 'URI');

        $this->expectException(Exception::class);

        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://graph> {
            <'.$longURI.'/s> <'.$longURI.'/p> <'.$longURI.'/o> ;
                             <'.$longURI.'/p2> <'.$longURI.'/o2> .
        ');
    }

    public function testInsertIntoListMoreComplex()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {
            _:b0  rdf:first  1 ;
                  rdf:rest   _:b1 .
            _:b1  rdf:first  2 ;
                  rdf:rest   _:b2 .
            _:b2  rdf:first  3 ;
                  rdf:rest   rdf:nil .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');

        $this->assertEquals(
            [
                [
                    's' => $res['result']['rows'][0]['s'],
                    's type' => 'bnode',
                    'p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first',
                    'p type' => 'uri',
                    'o' => '1',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
                [
                    's' => $res['result']['rows'][1]['s'],
                    's type' => 'bnode',
                    'p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',
                    'p type' => 'uri',
                    'o' => $res['result']['rows'][1]['o'],
                    'o type' => 'bnode',
                ],
                [
                    's' => $res['result']['rows'][2]['s'],
                    's type' => 'bnode',
                    'p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first',
                    'p type' => 'uri',
                    'o' => '2',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
                [
                    's' => $res['result']['rows'][3]['s'],
                    's type' => 'bnode',
                    'p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',
                    'p type' => 'uri',
                    'o' => $res['result']['rows'][3]['o'],
                    'o type' => 'bnode',
                ],
                [
                    's' => $res['result']['rows'][4]['s'],
                    's type' => 'bnode',
                    'p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first',
                    'p type' => 'uri',
                    'o' => '3',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
                ],
                [
                    's' => $res['result']['rows'][5]['s'],
                    's type' => 'bnode',
                    'p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',
                    'p type' => 'uri',
                    'o' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil',
                    'o type' => 'uri',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testInsertIntoConstruct()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> CONSTRUCT {
            <http://baz> <http://location> "Leipzig" .
            <http://baz2> <http://location> "Grimma" .
        }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');
        $this->assertEquals(2, \count($res['result']['rows']));
    }

    public function testInsertIntoWhere()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> CONSTRUCT {
            <http://baz> <http://location> "Leipzig" .
            <http://baz2> <http://location> "Grimma" .
        } WHERE {
            ?s <http://location> "Leipzig" .
        }');

        // we expect that 1 element gets added to the store, because of the WHERE clause.
        // but store added none.
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> {?s ?p ?o.}');
        $this->assertEquals(2, \count($res['result']['rows']));

        $this->markTestSkipped(
            'Store does not check the WHERE clause when inserting data.'
            .' Too many triples were added.'
            .\PHP_EOL
            .\PHP_EOL.'FYI: https://www.w3.org/Submission/SPARQL-Update/#sec_examples and '
            .\PHP_EOL.'https://github.com/semsol/arc2/wiki/SPARQL-#insert-example'
        );
    }

    public function testInsertInto2GraphsSameTriples()
    {
        /*
         * Test behavior if same triple get inserted into two different graphs:
         * 1. add
         * 2. check additions
         * 3. delete graph2 content
         * 4. check again
         */

        $triple = '<http://foo> <http://location> "Leipzig" .';
        $this->subjectUnderTest->query('INSERT INTO <http://graph1/> {'.$triple.'}');
        $this->subjectUnderTest->query('INSERT INTO <http://graph2/> {'.$triple.'}');

        // check additions (graph1)
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph1/> {?s ?p ?o.}');
        $this->assertEquals(1, \count($res['result']['rows']));

        // check additions (graph2)
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph2/> {?s ?p ?o.}');
        $this->assertEquals(1, \count($res['result']['rows']));

        /*
         * test isolation by removing the triple from graph2
         */
        $this->subjectUnderTest->query('DELETE FROM <http://graph2/>');

        // check triples (graph1)
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph1/> {?s ?p ?o.}');
        $this->assertEquals(1, \count($res['result']['rows']));

        // check triples (graph2)
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph2/> {?s ?p ?o.}');
        $this->assertEquals(0, \count($res['result']['rows']));
    }

    /**
     * Tests old behavior of ARC2 store: its SQLite in-memory implementation was not able
     * to recognize all triples added by separate query calls.
     */
    public function testMultipleInsertsSameStore()
    {
        // add triples in separate query calls
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {<http://a> <http://b> <http://c> . }');
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {<http://a2> <http://b2> "c2"@de. }');
        $this->subjectUnderTest->query('INSERT INTO <http://ex/> {<http://a3> <http://b3> "c3"^^xsd:string . }');

        // check result
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> WHERE {?s ?p ?o.}');

        $this->assertEquals(3, \count($res['result']['rows']));

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
                    's' => 'http://a2',
                    's type' => 'uri',
                    'p' => 'http://b2',
                    'p type' => 'uri',
                    'o' => 'c2',
                    'o type' => 'literal',
                    'o lang' => 'de',
                ],
                [
                    's' => 'http://a3',
                    's type' => 'uri',
                    'p' => 'http://b3',
                    'p type' => 'uri',
                    'o' => 'c3',
                    'o type' => 'literal',
                    'o datatype' => 'http://www.w3.org/2001/XMLSchema#string',
                ],
            ],
            $res['result']['rows']
        );
    }

    public function testMultipleInsertQueriesInDifferentGraphs()
    {
        $this->subjectUnderTest->query('INSERT INTO <http://graph1/> {<http://foo/1> <http://foo/2> <http://foo/3> . }');
        $this->subjectUnderTest->query('INSERT INTO <http://graph2/> {<http://foo/4> <http://foo/5> <http://foo/6> . }');
        $this->subjectUnderTest->query('INSERT INTO <http://graph2/> {<http://foo/a> <http://foo/b> <http://foo/c> . }');

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph1/> WHERE {?s ?p ?o.}');
        $this->assertEquals(1, \count($res['result']['rows']));

        $res = $this->subjectUnderTest->query('SELECT * FROM <http://graph2/> WHERE {?s ?p ?o.}');
        $this->assertEquals(2, \count($res['result']['rows']));

        $res = $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}');
        $this->assertEquals(3, \count($res['result']['rows']));
    }

    /**
     * Adds bulk of triples to test behavior.
     * May take at least one second to finish.
     */
    public function testAdditionOfManyTriples()
    {
        $amount = 3000;

        $startTime = microtime(true);

        // add triples in separate query calls
        for ($i = 0; $i < $amount; ++$i) {
            $this->subjectUnderTest->query('INSERT INTO <http://ex/> {
                <http://a> <http://b> <http://c'.$i.'> .
            }');
        }

        // check result
        $res = $this->subjectUnderTest->query('SELECT * FROM <http://ex/> WHERE {?s ?p ?o.}');

        $this->assertEquals($amount, \count($res['result']['rows']));

        $timeUsed = microtime(true) - $startTime;
        $info = 'Test took longer than expected: '.$timeUsed.' sec.';
        $this->assertTrue(2 > $timeUsed, $info);
    }
}

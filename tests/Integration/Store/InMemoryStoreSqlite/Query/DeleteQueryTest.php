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
 * Tests for query method - focus on DELETE queries.
 */
class DeleteQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new Logger());
    }

    protected function runSPOQuery($g = null)
    {
        return null == $g
            ? $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}')
            : $this->subjectUnderTest->query('SELECT * FROM <'.$g.'> WHERE {?s ?p ?o.}');
    }

    public function testDelete()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/1> {
            <http://s> <http://p1> "baz" .
        }');
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/2> {
            <http://s> <http://p1> "bar" .
        }');

        $this->assertEquals(2, \count($this->runSPOQuery()['result']['rows']));

        $this->subjectUnderTest->query('DELETE {<http://s> ?p ?o .}');

        $this->assertEquals(0, \count($this->runSPOQuery()['result']['rows']));
    }

    public function testDelete2()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/1> {
            <http://s> <http://p1> "baz" .
        }');
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/2> {
            <http://s> <http://p2> "bar" .
        }');

        $this->assertEquals(2, \count($this->runSPOQuery()['result']['rows']));

        $this->subjectUnderTest->query('DELETE {<http://s> <http://p1> ?o .}');

        $this->assertEquals(1, \count($this->runSPOQuery()['result']['rows']));
    }

    public function testDeleteAGraph()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/1> {
            <http://s> <http://p1> "baz" .
        }');

        $this->assertEquals(1, \count($this->runSPOQuery()['result']['rows']));

        $this->subjectUnderTest->query('DELETE FROM <http://example.com/1>');

        $this->assertEquals(0, \count($this->runSPOQuery()['result']['rows']));
    }

    public function testDeleteWhere()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/1> {
            <http://s> <http://to-delete> 1, 2 .
            <http://s> <http://to-check> 1, 2 .
            <http://s> rdf:type <http://Test> .
        }');

        $this->assertEquals(5, \count($this->runSPOQuery()['result']['rows']));

        $this->subjectUnderTest->query('DELETE {
            <http://s> <http://to-delete> 1, 2 .
        } WHERE {
            <http://s> <http://to-check> 1, 2 .
        }');

        $this->assertEquals(3, \count($this->runSPOQuery()['result']['rows']));
    }

    public function testDeleteWhereWithBlankNode()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/1> {
            _:a <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://Person> ;
                <http://foo> <http://bar > .
        }');

        $this->assertEquals(2, \count($this->runSPOQuery()['result']['rows']));

        $this->subjectUnderTest->query('DELETE {
            _:a ?p ?o .
        } WHERE {
            _:a <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://Person> .
        }');

        // first we check the expected behavior and afterwards skip to notice the
        // developer about it.
        $this->assertEquals(2, \count($this->runSPOQuery()['result']['rows']));
        $this->markTestSkipped('DELETE queries with blank nodes are not working.');
    }

    public function testDeleteFromWhere()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/1> {
            <http://s> <http://to-delete> 1, 2 .
            <http://s> <http://to-check> 1, 2 .
            <http://s> rdf:type <http://Test> .
        }');

        $this->assertEquals(5, \count($this->runSPOQuery('http://example.com/1')['result']['rows']));

        $this->subjectUnderTest->query('DELETE FROM <http://example.com/1> {
            <http://s> <http://to-delete> 1, 2 .
        } WHERE {
            <http://s> <http://to-check> 1, 2 .
        }');

        $this->assertEquals(3, \count($this->runSPOQuery('http://example.com/1')['result']['rows']));
    }
}

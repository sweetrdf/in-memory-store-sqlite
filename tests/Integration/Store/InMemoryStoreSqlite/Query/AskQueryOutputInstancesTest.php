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
use sweetrdf\InMemoryStoreSqlite\Rdf\Literal;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Tests for query method - focus on ASK queries.
 *
 * Output format is: instances
 */
class AskQueryOutputInstancesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new LoggerPool());
    }

    public function testAsk()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $sparql = 'ASK FROM <http://example.com/> {<http://s> <http://p1> ?o.}';
        $result = $this->subjectUnderTest->query($sparql, 'instances');
        $this->assertEquals(new Literal(true), $result);

        $sparql = 'ASK FROM <http://example.com/> {<http://foo> <http://bar> ?o.}';
        $result = $this->subjectUnderTest->query($sparql, 'instances');
        $this->assertEquals(new Literal(false), $result);
    }
}

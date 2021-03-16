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
use Tests\ARC2_TestCase;

/**
 * Tests for query method - focus on ASK queries.
 */
class AskQueryTest extends ARC2_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new Logger());
    }

    public function testAskDefaultGraph()
    {
        // test data
        $this->fixture->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->fixture->query('ASK {<http://s> <http://p1> ?o.}');
        $this->assertEquals(
            [
                'query_type' => 'ask',
                'result' => true,
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }

    public function testAskGraphSpecified()
    {
        // test data
        $this->fixture->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->fixture->query('ASK FROM <http://example.com/> {<http://s> <http://p1> ?o.}');
        $this->assertEquals(
            [
                'query_type' => 'ask',
                'result' => true,
                'query_time' => $res['query_time'],
            ],
            $res
        );
    }
}

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

use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Tests for query method - focus on ASK queries.
 */
class AskQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = InMemoryStoreSqlite::createInstance();
    }

    public function testAskDefaultGraph()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('ASK {<http://s> <http://p1> ?o.}');
        $this->assertEquals(
            [
                'query_type' => 'ask',
                'result' => true,
            ],
            $res
        );
    }

    public function testAskGraphSpecified()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('ASK FROM <http://example.com/> {<http://s> <http://p1> ?o.}');
        $this->assertEquals(
            [
                'query_type' => 'ask',
                'result' => true,
            ],
            $res
        );
    }
}

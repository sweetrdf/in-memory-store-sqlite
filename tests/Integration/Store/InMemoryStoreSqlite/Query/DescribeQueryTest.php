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

namespace Tests\Integration\Store\InMemoryStoreSqlite\Query;

use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Tests for query method - focus on DESCRIBE queries.
 */
class DescribeQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = InMemoryStoreSqlite::createInstance();
    }

    public function testDescribeDefaultGraph()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('DESCRIBE <http://s>');
        $this->assertEquals(
            [
                'query_type' => 'describe',
                'result' => [
                    'http://s' => [
                        'http://p1' => [
                            [
                                'value' => 'baz',
                                'type' => 'literal',
                            ],
                        ],
                    ],
                ],
            ],
            $res
        );
    }

    public function testDescribeWhereDefaultGraph()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('DESCRIBE ?s WHERE {?s ?p "baz".}');
        $this->assertEquals(
            [
                'query_type' => 'describe',
                'result' => [
                    'http://s' => [
                        'http://p1' => [
                            [
                                'value' => 'baz',
                                'type' => 'literal',
                            ],
                        ],
                    ],
                ],
            ],
            $res
        );
    }

    public function testDescribeWhereDefaultGraph2()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
        }');

        $res = $this->subjectUnderTest->query('DESCRIBE * WHERE {?s ?p "baz".}');
        $this->assertEquals(
            [
                'query_type' => 'describe',
                'result' => [
                    'http://s' => [
                        'http://p1' => [
                            [
                                'value' => 'baz',
                                'type' => 'literal',
                            ],
                        ],
                    ],
                ],
            ],
            $res
        );
    }
}

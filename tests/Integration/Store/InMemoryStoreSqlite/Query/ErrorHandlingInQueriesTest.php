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
 * Tests for query method - focus on how the system reacts, when errors occur.
 */
class ErrorHandlingInQueriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new LoggerPool());
    }

    /**
     * What if a result variable is not used in query.
     */
    public function testResultVariableNotUsedInQuery()
    {
        $res = $this->subjectUnderTest->query('
            SELECT ?not_used_in_query ?s WHERE {
                ?s ?p ?o .
            }
        ');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'not_used_in_query', 's',
                    ],
                    'rows' => [
                    ],
                ],
                'query_time' => $res['query_time'],
            ],
            $res
        );

        // TODO not bad if count is higher than 2
        $errors = \count($this->subjectUnderTest->getLoggerPool()->getEntriesFromAllLoggerInstances());
        $this->assertEquals(2, $errors);
    }
}

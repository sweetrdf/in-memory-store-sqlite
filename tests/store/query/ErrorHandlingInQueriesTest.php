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

namespace Tests\store\query;

use sweetrdf\InMemoryStoreSqlite\Logger;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\ARC2_TestCase;

/**
 * Tests for query method - focus on how the system reacts, when errors occur.
 */
class ErrorHandlingInQueriesTest extends ARC2_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new Logger());
    }

    /**
     * What if a result variable is not used in query.
     */
    public function testResultVariableNotUsedInQuery()
    {
        $res = $this->fixture->query('
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
        $this->assertEquals(2, \count($this->fixture->getLogger()->getEntries()));
    }
}

<?php

/*
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-3 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integration\Store\QueryHandler;

use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\InsertQueryHandler;
use Tests\TestCase;

class InsertIntoQueryHandlerTest extends TestCase
{
    private function getSubjectUnderTest(): InsertQueryHandler
    {
        /** @var \sweetrdf\InMemoryStoreSqlite\Log\Logger */
        $logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();

        return new InsertQueryHandler(InMemoryStoreSqlite::createInstance(), $logger);
    }

    public function test1()
    {
        $sut = $this->getSubjectUnderTest();
        $graphIri = 'http://test/';

        $sut->runQuery([
            'query' => [
                'construct_triples' => [
                    [
                        's' => 'http://www.w3.org/2009/manifest#agg05',
                        's_type' => 'uri',
                        'p' => 'http://www.w3.org/2001/sw/DataAccess/tests/test-manifest#action',
                        'o' => '_:28848a60b33',
                        'o_type' => 'bnode',
                        'o_datatype' => '',
                        'o_lang' => '',
                    ],
                    [
                        's' => '_:28848a60b33',
                        's_type' => 'bnode',
                        'p' => 'http://www.w3.org/2001/sw/DataAccess/tests/test-query#query',
                        'o' => 'file:///var/www/html/in-memory-store-sqlite/tests/aggregates/agg05.rq',
                        'o_type' => 'uri',
                        'o_datatype' => '',
                        'o_lang' => '',
                    ],
                ],
                'target_graph' => $graphIri,
            ],
        ]);

        $query = 'SELECT * FROM <'.$graphIri.'> WHERE { ?s ?p ?o }';
        $result = $sut->getStore()->query($query);
        $this->assertCount(2, $result);
    }
}

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

namespace Tests\Unit\Store\QueryHandler;

use sweetrdf\InMemoryStoreSqlite\KeyValueBag;
use sweetrdf\InMemoryStoreSqlite\Log\LoggerPool;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\LoadQueryHandler;
use Tests\TestCase;

class LoadQueryHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $loggerPool = new LoggerPool();

        $store = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), $loggerPool, new KeyValueBag());

        $this->subjectUnderTest = new LoadQueryHandler($store, $loggerPool->createNewLogger('test'));
    }

    /*
     * Tests for getOComp
     */

    /**
     * Tests to behavior, if a datetime string was given.
     */
    public function testGetOComp()
    {
        // case with +hourse
        $string = '2009-05-28T18:03:38+09:00';
        $this->assertEquals('2009-05-28T09:03:38Z', $this->subjectUnderTest->getOComp($string));

        // GMT case
        $string = '2009-05-28T18:03:38GMT';
        $this->assertEquals('2009-05-28T18:03:38Z', $this->subjectUnderTest->getOComp($string));
    }
}

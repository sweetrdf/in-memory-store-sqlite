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

namespace Tests\unit\store;

use sweetrdf\InMemoryStoreSqlite\Logger;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\LoadQueryHandler;
use Tests\ARC2_TestCase;

class ARC2_StoreLoadQueryHandlerTest extends ARC2_TestCase
{
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new Logger());

        $this->fixture = new LoadQueryHandler($this->store);
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
        $this->assertEquals('2009-05-28T09:03:38Z', $this->fixture->getOComp($string));

        // GMT case
        $string = '2009-05-28T18:03:38GMT';
        $this->assertEquals('2009-05-28T18:03:38Z', $this->fixture->getOComp($string));
    }
}

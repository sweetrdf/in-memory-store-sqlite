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

use ARC2_Store;
use ARC2_StoreLoadQueryHandler;
use sweetrdf\InMemoryStoreSqlite\Logger;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use Tests\ARC2_TestCase;

class ARC2_StoreLoadQueryHandlerTest extends ARC2_TestCase
{
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new ARC2_Store(new PDOSQLiteAdapter(), new Logger());

        $this->fixture = new ARC2_StoreLoadQueryHandler($this->store);
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

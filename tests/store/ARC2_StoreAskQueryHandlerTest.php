<?php

/*
 *  This file is part of the quickrdf/InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Tests\store;

use Tests\ARC2_TestCase;

class ARC2_StoreAskQueryHandlerTest extends ARC2_TestCase
{
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = \ARC2::getStore($this->dbConfig);

        $this->fixture = new \ARC2_StoreAskQueryHandler($this->store->a, $this->store);
    }

    /*
     * Tests for __init
     */

    public function testInit()
    {
        $this->fixture = new \ARC2_StoreAskQueryHandler($this->store->a, $this->store);
        $this->fixture->__init();
        $this->assertEquals($this->store, $this->fixture->store);
    }
}
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

namespace Tests\store\ARC2_StoreLoadQueryHandler;

use ARC2_StoreLoadQueryHandler;
use Tests\ARC2_TestCase;

class ARC2_StoreLoadQueryHandlerTest extends ARC2_TestCase
{
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = \ARC2::getStore($this->dbConfig);

        $this->fixture = new ARC2_StoreLoadQueryHandler($this->store, $this);
    }

    /**
     * Tests behavior, if has to extend columns.
     */
    public function testExtendColumns(): void
    {
        $this->fixture->setStore($this->store);
        $this->fixture->column_type = 'mediumint';
        $this->fixture->max_term_id = 16750001;

        $this->assertEquals(16750001, $this->fixture->getStoredTermID('', '', ''));
    }
}

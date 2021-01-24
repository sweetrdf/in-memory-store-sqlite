<?php

/*
 *  This file is part of the InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Tests\db_adapter_depended\store\ARC2_StoreLoadQueryHandler;

use ARC2_StoreLoadQueryHandler;
use PDO;
use quickrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use Tests\ARC2_TestCase;

class ARC2_StoreLoadQueryHandlerTest extends ARC2_TestCase
{
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = \ARC2::getStore($this->dbConfig);
        $this->store->createDBCon();

        // remove all tables
        $this->store->getDBObject()->deleteAllTables();
        $this->store->setUp();

        $this->fixture = new ARC2_StoreLoadQueryHandler($this->store, $this);
    }

    protected function tearDown(): void
    {
        $this->store->closeDBCon();
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

        // PDO + SQLite
        if ($this->store->getDBObject() instanceof PDOSQLiteAdapter) {
        } else {
            // MySQL
            $table_fields = $this->store->getDBObject()->fetchList('DESCRIBE arc_g2t');
            $this->assertEquals('int(10) unsigned', $table_fields[0]['Type']);
        }
    }
}

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

namespace Tests\integration;

use Exception;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use Tests\ARC2_TestCase;

class PDOSQLiteAdapterTest extends ARC2_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new PDOSQLiteAdapter();
    }

    /*
     * Tests for connect
     */

    public function testConnectCreateNewConnection()
    {
        $this->fixture->disconnect();

        // do explicit reconnect
        $this->fixture = new PDOSQLiteAdapter();

        $this->fixture->exec('CREATE TABLE test (id INTEGER)');
        $this->assertEquals([], $this->fixture->fetchList('SELECT * FROM test;'));
    }

    public function testEscape()
    {
        $this->assertEquals('"hallo"', $this->fixture->escape('"hallo"'));
    }

    /*
     * Tests for exec
     */

    public function testExec()
    {
        $this->fixture->exec('CREATE TABLE users (id INTEGER, name TEXT NOT NULL)');
        $this->fixture->exec('INSERT INTO users (id, name) VALUES (1, "foobar");');
        $this->fixture->exec('INSERT INTO users (id, name) VALUES (2, "foobar2");');

        $this->assertEquals(2, $this->fixture->exec('DELETE FROM users;'));
    }

    /*
     * Tests for fetchRow
     */

    public function testFetchRow()
    {
        // valid query
        $this->fixture->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->assertFalse($this->fixture->fetchRow('SELECT * FROM users'));

        // add data
        $this->fixture->exec('INSERT INTO users (id, name) VALUES (1, "foobar");');
        $this->assertEquals(
            [
                'id' => 1,
                'name' => 'foobar',
            ],
            $this->fixture->fetchRow('SELECT * FROM users WHERE id = 1;')
        );
    }

    /*
     * Tests for fetchList
     */

    public function testFetchList()
    {
        // valid query
        $sql = 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)';
        $this->fixture->exec($sql);
        $this->assertEquals([], $this->fixture->fetchList('SELECT * FROM users'));

        // add data
        $this->fixture->exec('INSERT INTO users (id, name) VALUES (1, "foobar");');
        $this->assertEquals(
            [
                [
                    'id' => 1,
                    'name' => 'foobar',
                ],
            ],
            $this->fixture->fetchList('SELECT * FROM users')
        );
    }

    public function testGetPDO()
    {
        $this->assertTrue($this->fixture->getPDO() instanceof \PDO);
    }

    /*
     * Tests for getNumberOfRows
     */

    public function testGetNumberOfRows()
    {
        // create test table
        $this->fixture->exec('CREATE TABLE pet (name TEXT)');
        $this->fixture->exec('INSERT INTO pet VALUES ("cat")');
        $this->fixture->exec('INSERT INTO pet VALUES ("dog")');

        $this->assertEquals(2, $this->fixture->getNumberOfRows('SELECT * FROM pet;'));
    }

    public function testGetNumberOfRowsInvalidQuery()
    {
        $this->expectException('Exception');

        $this->fixture->getNumberOfRows('SHOW TABLES of x');
    }

    /*
     * Tests for getServerVersion
     */

    public function testGetServerVersion()
    {
        // check server version
        $this->assertEquals(
            1,
            preg_match('/[0-9]{1,}\.[0-9]{1,}\.[0-9]{1,}/',
            'Found: '.$this->fixture->getServerVersion())
        );
    }

    /*
     * Tests for insert
     */

    public function testInsert()
    {
        // create test table
        $this->fixture->exec('CREATE TABLE pet (name TEXT)');

        $this->fixture->insert('pet', ['name' => 'test1']);
        $this->fixture->insert('pet', ['name' => 'test2']);

        $this->assertEquals(2, $this->fixture->getNumberOfRows('SELECT * FROM pet;'));
    }

    public function testInsertTableNameSpecialChars()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid table name given.');

        // create test table
        $this->fixture->exec('CREATE TABLE pet (name TEXT)');

        $this->fixture->insert('pet"', ['name' => 'test1']);
    }

    public function testQuery()
    {
        // valid query
        $sql = 'CREATE TABLE MyGuests (id INTEGER PRIMARY KEY AUTOINCREMENT)';
        $this->fixture->exec($sql);

        $foundTable = false;
        foreach ($this->fixture->getAllTables() as $table) {
            if ('MyGuests' == $table) {
                $foundTable = true;
                break;
            }
        }
        $this->assertTrue($foundTable, 'Expected table not found.');
    }

    public function testQueryInvalid()
    {
        $this->expectException('Exception');

        // invalid query
        $this->assertFalse($this->fixture->simpleQuery('invalid query'));
    }
}

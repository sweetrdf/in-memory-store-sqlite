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

namespace Tests\Integration;

use Exception;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use Tests\TestCase;

class PDOSQLiteAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = new PDOSQLiteAdapter();
    }

    /*
     * Tests for connect
     */

    public function testConnectCreateNewConnection()
    {
        $this->subjectUnderTest->disconnect();

        // do explicit reconnect
        $this->subjectUnderTest = new PDOSQLiteAdapter();

        $this->subjectUnderTest->exec('CREATE TABLE test (id INTEGER)');
        $this->assertEquals([], $this->subjectUnderTest->fetchList('SELECT * FROM test;'));
    }

    public function testEscape()
    {
        $this->assertEquals('"hallo"', $this->subjectUnderTest->escape('"hallo"'));
    }

    /*
     * Tests for exec
     */

    public function testExec()
    {
        $this->subjectUnderTest->exec('CREATE TABLE users (id INTEGER, name TEXT NOT NULL)');
        $this->subjectUnderTest->exec('INSERT INTO users (id, name) VALUES (1, "foobar");');
        $this->subjectUnderTest->exec('INSERT INTO users (id, name) VALUES (2, "foobar2");');

        $this->assertEquals(2, $this->subjectUnderTest->exec('DELETE FROM users;'));
    }

    /*
     * Tests for fetchRow
     */

    public function testFetchRow()
    {
        // valid query
        $this->subjectUnderTest->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->assertFalse($this->subjectUnderTest->fetchRow('SELECT * FROM users'));

        // add data
        $this->subjectUnderTest->exec('INSERT INTO users (id, name) VALUES (1, "foobar");');
        $this->assertEquals(
            [
                'id' => 1,
                'name' => 'foobar',
            ],
            $this->subjectUnderTest->fetchRow('SELECT * FROM users WHERE id = 1;')
        );
    }

    /*
     * Tests for fetchList
     */

    public function testFetchList()
    {
        // valid query
        $sql = 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)';
        $this->subjectUnderTest->exec($sql);
        $this->assertEquals([], $this->subjectUnderTest->fetchList('SELECT * FROM users'));

        // add data
        $this->subjectUnderTest->exec('INSERT INTO users (id, name) VALUES (1, "foobar");');
        $this->assertEquals(
            [
                [
                    'id' => 1,
                    'name' => 'foobar',
                ],
            ],
            $this->subjectUnderTest->fetchList('SELECT * FROM users')
        );
    }

    public function testGetPDO()
    {
        $this->assertTrue($this->subjectUnderTest->getPDO() instanceof \PDO);
    }

    /*
     * Tests for getNumberOfRows
     */

    public function testGetNumberOfRows()
    {
        // create test table
        $this->subjectUnderTest->exec('CREATE TABLE pet (name TEXT)');
        $this->subjectUnderTest->exec('INSERT INTO pet VALUES ("cat")');
        $this->subjectUnderTest->exec('INSERT INTO pet VALUES ("dog")');

        $this->assertEquals(2, $this->subjectUnderTest->getNumberOfRows('SELECT * FROM pet;'));
    }

    public function testGetNumberOfRowsInvalidQuery()
    {
        $this->expectException('Exception');

        $this->subjectUnderTest->getNumberOfRows('SHOW TABLES of x');
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
            'Found: '.$this->subjectUnderTest->getServerVersion())
        );
    }

    /*
     * Tests for insert
     */

    public function testInsert()
    {
        // create test table
        $this->subjectUnderTest->exec('CREATE TABLE pet (name TEXT)');

        $this->subjectUnderTest->insert('pet', ['name' => 'test1']);
        $this->subjectUnderTest->insert('pet', ['name' => 'test2']);

        $this->assertEquals(2, $this->subjectUnderTest->getNumberOfRows('SELECT * FROM pet;'));
    }

    public function testInsertTableNameSpecialChars()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid table name given.');

        // create test table
        $this->subjectUnderTest->exec('CREATE TABLE pet (name TEXT)');

        $this->subjectUnderTest->insert('pet"', ['name' => 'test1']);
    }

    public function testQuery()
    {
        // valid query
        $sql = 'CREATE TABLE MyGuests (id INTEGER PRIMARY KEY AUTOINCREMENT)';
        $this->subjectUnderTest->exec($sql);

        $foundTable = false;
        foreach ($this->subjectUnderTest->getAllTables() as $table) {
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
        $this->assertFalse($this->subjectUnderTest->simpleQuery('invalid query'));
    }
}

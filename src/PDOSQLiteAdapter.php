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

namespace sweetrdf\InMemoryStoreSqlite;

use Exception;
use PDO;

/**
 * PDO SQLite adapter.
 */
final class PDOSQLiteAdapter
{
    /**
     * @var \PDO
     */
    private $db;

    /**
     * @var int
     */
    private $lastRowCount = 0;

    /**
     * Sent queries.
     *
     * @var array
     */
    private $queries = [];

    public function __construct(string $dbName = null)
    {
        $this->checkRequirements();

        // set path to SQLite file
        if (!empty($dbName)) {
            $dsn = 'sqlite:'.$dbName;
        } else {
            // use in-memory
            $dsn = 'sqlite::memory:';
        }

        $this->db = new PDO($dsn);

        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // errors lead to exceptions
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // default fetch mode is associative
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        /*
         * define CONCAT function (otherwise SQLite will throw an exception)
         */
        $this->db->sqliteCreateFunction('CONCAT', function ($pattern, $string) {
            $result = '';

            foreach (\func_get_args() as $str) {
                $result .= $str;
            }

            return $result;
        });

        /*
         * define REGEXP function (otherwise SQLite will throw an exception)
         */
        $this->db->sqliteCreateFunction('REGEXP', function ($pattern, $string) {
            if (0 < preg_match('/'.$pattern.'/i', $string)) {
                return true;
            }

            return false;
        }, 2);

        $this->createTables();
    }

    public function checkRequirements()
    {
        if (false == \extension_loaded('pdo_sqlite')) {
            throw new Exception('Extension pdo_sqlite is not loaded.');
        }
    }

    public function deleteAllTables(): void
    {
        $this->exec(
            'SELECT "drop table " || name || ";"
               FROM sqlite_master
              WHERE type = "table";'
        );
    }

    /**
     * Creates all required tables.
     */
    private function createTables(): void
    {
        // triple
        $sql = 'CREATE TABLE IF NOT EXISTS triple (
            t INTEGER PRIMARY KEY AUTOINCREMENT,
            s INTEGER UNSIGNED NOT NULL,
            p INTEGER UNSIGNED NOT NULL,
            o INTEGER UNSIGNED NOT NULL,
            o_lang_dt INTEGER UNSIGNED NOT NULL,
            o_comp TEXT NOT NULL,                       -- normalized value for ORDER BY operations
            s_type INTEGER UNSIGNED NOT NULL DEFAULT 0, -- uri/bnode => 0/1
            o_type INTEGER UNSIGNED NOT NULL DEFAULT 0, -- uri/bnode/literal => 0/1/2
            misc INTEGER NOT NULL DEFAULT 0             -- temporary flags
        )';

        $this->exec($sql);

        // g2t
        $sql = 'CREATE TABLE IF NOT EXISTS g2t (
            g INTEGER UNSIGNED NOT NULL,
            t INTEGER UNSIGNED NOT NULL,
            UNIQUE (g,t)
        )';

        $this->exec($sql);

        // id2val
        $sql = 'CREATE TABLE IF NOT EXISTS id2val (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            misc INTEGER UNSIGNED NOT NULL DEFAULT 0,
            val TEXT NOT NULL,
            val_type INTEGER NOT NULL DEFAULT 0,     -- uri/bnode/literal => 0/1/2
            UNIQUE (id,val_type)
        )';

        $this->exec($sql);

        // s2val
        $sql = 'CREATE TABLE IF NOT EXISTS s2val (
            id INTEGER UNSIGNED NOT NULL,
            misc INTEGER NOT NULL DEFAULT 0,
            val_hash TEXT NOT NULL,
            val TEXT NOT NULL,
            UNIQUE (id)
        )';

        $this->exec($sql);

        // o2val
        $sql = 'CREATE TABLE IF NOT EXISTS o2val (
            id INTEGER NOT NULL,
            misc INTEGER UNSIGNED NOT NULL DEFAULT 0,
            val_hash TEXT NOT NULL,
            val TEXT NOT NULL,
            UNIQUE (id)
        )';

        $this->exec($sql);

        // setting
        $sql = 'CREATE TABLE IF NOT EXISTS setting (
            k TEXT NOT NULL,
            val TEXT NOT NULL,
            UNIQUE (k)
        )';

        $this->exec($sql);
    }

    /**
     * It gets all tables from the current database.
     */
    public function getAllTables(): array
    {
        $tables = $this->fetchList('SELECT name FROM sqlite_master WHERE type="table";');
        $result = [];
        foreach ($tables as $table) {
            // ignore SQLite tables
            if (false !== strpos($table['name'], 'sqlite_')) {
                continue;
            }
            $result[] = $table['name'];
        }

        return $result;
    }

    public function getConnectionId()
    {
        return null;
    }

    public function getDBSName()
    {
        return 'sqlite';
    }

    public function getServerInfo()
    {
        return null;
    }

    public function getServerVersion()
    {
        return $this->fetchRow('select sqlite_version()')['sqlite_version()'];
    }

    public function getAdapterName()
    {
        return 'pdo';
    }

    public function getAffectedRows(): int
    {
        return $this->lastRowCount;
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        // FYI: https://stackoverflow.com/questions/18277233/pdo-closing-connection
        $this->db = null;
    }

    public function escape($value)
    {
        $quoted = $this->db->quote($value);

        /*
         * fixes the case, that we have double quoted strings like:
         *      ''x1''
         *
         * remember, this value will be surrounded by quotes later on!
         * so we don't send it back with quotes around.
         */
        if ("'" == substr($quoted, 0, 1)) {
            $quoted = substr($quoted, 1, \strlen($quoted) - 2);
        }

        return $quoted;
    }

    /**
     * @param string $sql
     *
     * @return array
     */
    public function fetchList($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'fetchList',
        ];

        if (null == $this->db) {
            $this->connect();
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();

        return $rows;
    }

    public function fetchRow($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'fetchRow',
        ];

        if (null == $this->db) {
            $this->connect();
        }

        $row = false;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (0 < \count($rows)) {
            $row = array_values($rows)[0];
        }
        $stmt->closeCursor();

        return $row;
    }

    public function getConnection()
    {
        return $this->db;
    }

    public function getErrorCode()
    {
        return $this->db->errorCode();
    }

    public function getErrorMessage()
    {
        return $this->db->errorInfo()[2];
    }

    public function getLastInsertId()
    {
        return $this->db->lastInsertId();
    }

    public function getNumberOfRows($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'getNumberOfRows',
        ];

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rowCount = \count($stmt->fetchAll());
        $stmt->closeCursor();

        return $rowCount;
    }

    /**
     * @param string $sql Query
     *
     * @return bool true if query ran fine, false otherwise
     */
    public function simpleQuery($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'simpleQuery',
        ];

        if (false === $this->db instanceof \PDO) {
            $this->connect();
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $this->lastRowCount = $stmt->rowCount();
        $stmt->closeCursor();

        return true;
    }

    /**
     * Encapsulates internal PDO::exec call. This allows us to extend it, e.g. with caching functionality.
     *
     * @param string $sql
     *
     * @return int number of affected rows
     */
    public function exec($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'exec',
        ];

        if (null == $this->db) {
            $this->connect();
        }

        return $this->db->exec($sql);
    }
}

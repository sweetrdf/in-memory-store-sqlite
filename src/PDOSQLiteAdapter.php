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
class PDOSQLiteAdapter
{
    private ?\PDO $db;

    private int $lastRowCount = 0;

    /**
     * Sent queries.
     */
    private array $queries = [];

    public function __construct()
    {
        $this->checkRequirements();

        // use in-memory
        $dsn = 'sqlite::memory:';

        $this->db = new PDO($dsn);

        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // errors lead to exceptions
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // default fetch mode is associative
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        /*
         * These PRAGMAs may speed up insert operations a bit.
         * Because database runs exclusively in memory for a process
         * journal mode etc. is not relevant.
         */
        $this->db->query('PRAGMA synchronous = OFF;');
        $this->db->query('PRAGMA journal_mode = OFF;');
        $this->db->query('PRAGMA locking_mode = EXCLUSIVE;');
        $this->db->query('PRAGMA page_size = 4096;');

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
            o_type INTEGER UNSIGNED NOT NULL DEFAULT 0  -- uri/bnode/literal => 0/1/2
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
            val TEXT NOT NULL,
            val_type INTEGER NOT NULL DEFAULT 0, -- uri/bnode/literal => 0/1/2
            UNIQUE (id,val_type)
        )';

        $this->exec($sql);

        // s2val
        $sql = 'CREATE TABLE IF NOT EXISTS s2val (
            id INTEGER UNSIGNED NOT NULL,
            val_hash TEXT NOT NULL,
            val TEXT NOT NULL,
            UNIQUE (id)
        )';

        $this->exec($sql);

        // o2val
        $sql = 'CREATE TABLE IF NOT EXISTS o2val (
            id INTEGER NOT NULL,
            val_hash TEXT NOT NULL,
            val TEXT NOT NULL,
            UNIQUE (id)
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

    public function getServerVersion()
    {
        return $this->fetchRow('select sqlite_version()')['sqlite_version()'];
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

    public function fetchList(string $sql, array $params = []): array
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'fetchList',
        ];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();

        return $rows;
    }

    /**
     * @return bool|array
     */
    public function fetchRow(string $sql, array $params = [])
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'fetchRow',
        ];

        $row = false;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (0 < \count($rows)) {
            $row = array_values($rows)[0];
        }
        $stmt->closeCursor();

        return $row;
    }

    public function getPDO()
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

    public function simpleQuery(string $sql, array $params = []): bool
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'simpleQuery',
        ];

        $stmt = $this->db->prepare($sql, $params);
        $stmt->execute();
        $this->lastRowCount = $stmt->rowCount();
        $stmt->closeCursor();

        return true;
    }

    /**
     * Encapsulates internal PDO::exec call.
     * This allows us to extend it, e.g. with caching functionality.
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

        return $this->db->exec($sql);
    }

    /**
     * @return int ID of new entry
     *
     * @throws Exception if invalid table name was given
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);

        // we reject fishy table names
        if (1 !== preg_match('/^[a-zA-Z0-9_]+$/i', $table)) {
            throw new Exception('Invalid table name given.');
        }

        /*
         * start building SQL
         */
        $sql = 'INSERT INTO '.$table.' ('.implode(', ', $columns);
        $sql .= ') VALUES (';

        // add placeholders for each value; collect values
        $placeholders = [];
        $params = [];
        foreach ($data as $v) {
            $placeholders[] = '?';
            $params[] = $v;
        }
        $sql .= implode(', ', $placeholders);

        $sql .= ')';

        /*
         * SQL looks like the following now:
         *      INSERT INTO foo (bar) (?)
         */

        // Setup and run prepared statement
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->db->lastInsertId();
    }
}

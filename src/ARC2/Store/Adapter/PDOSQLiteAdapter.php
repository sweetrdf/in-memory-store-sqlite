<?php

/**
 * Adapter to enable usage of PDO functions.
 *
 * @author Benjamin Nowack <bnowack@semsol.com>
 * @author Konrad Abicht <konrad.abicht@pier-and-peer.com>
 * @license W3C Software License and GPL
 * @homepage <https://github.com/semsol/arc2>
 */

namespace ARC2\Store\Adapter;

use Exception;
use PDO;

/**
 * PDO Adapter - Handles database operations using PDO.
 *
 * This adapter doesn't support SQLite, please use PDOSQLiteAdapter instead.
 */
class PDOSQLiteAdapter
{
    protected $configuration;
    protected $db;

    /**
     * @var int
     */
    protected $lastRowCount;

    /**
     * Sent queries.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * @param array $configuration Default is array(). Only use, if you have your own mysqli connection.
     */
    public function __construct(array $configuration = [])
    {
        $this->configuration = $configuration;
        $this->lastRowCount = 0;

        $this->checkRequirements();
    }

    public function checkRequirements()
    {
        if (false == \extension_loaded('pdo_sqlite')) {
            throw new Exception('Extension pdo_sqlite is not loaded.');
        }
    }

    /**
     * Connect to server or storing a given connection.
     *
     * @param PDO $existingConnection default is null
     */
    public function connect($existingConnection = null)
    {
        // reuse a given existing connection.
        // it assumes that $existingConnection is a PDO connection object
        if (null !== $existingConnection) {
            $this->db = $existingConnection;

        // create your own connection
        } elseif (false === $this->db instanceof PDO) {
            // set path to SQLite file
            if (
                isset($this->configuration['db_name'])
                && !empty($this->configuration['db_name'])
            ) {
                $dsn = 'sqlite:'.$this->configuration['db_name'];
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
        }

        return $this->db;
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

    public function getCollation()
    {
        return '';
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
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

    public function getStoreName()
    {
        if (isset($this->configuration['store_name'])) {
            return $this->configuration['store_name'];
        }

        return 'arc';
    }

    public function getTablePrefix()
    {
        $prefix = '';
        if (isset($this->configuration['db_table_prefix'])) {
            $prefix = $this->configuration['db_table_prefix'].'_';
        }

        $prefix .= $this->getStoreName().'_';

        return $prefix;
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

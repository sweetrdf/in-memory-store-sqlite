<?php

/**
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-2 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Benjamin Nowack
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sweetrdf\InMemoryStoreSqlite\Store\QueryHandler;

use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;

abstract class QueryHandler
{
    protected Logger $logger;

    protected InMemoryStoreSqlite $store;

    protected array $term_id_cache;

    protected string $xsd = NamespaceHelper::NAMESPACE_XSD;

    public function __construct(InMemoryStoreSqlite $store, Logger $logger)
    {
        $this->logger = $logger;
        $this->store = $store;
    }

    public function getStore(): InMemoryStoreSqlite
    {
        return $this->store;
    }

    public function getTermID($val, $term = '')
    {
        /* mem cache */
        if (!isset($this->term_id_cache) || (\count(array_keys($this->term_id_cache)) > 100)) {
            $this->term_id_cache = [];
        }
        if (!isset($this->term_id_cache[$term])) {
            $this->term_id_cache[$term] = [];
        }

        $tbl = preg_match('/^(s|o)$/', $term) ? $term.'2val' : 'id2val';
        /* cached? */
        if ((\strlen($val) < 100) && isset($this->term_id_cache[$term][$val])) {
            return $this->term_id_cache[$term][$val];
        }

        $r = 0;
        /* via hash */
        if (preg_match('/^(s2val|o2val)$/', $tbl)) {
            $rows = $this->store->getDBObject()->fetchList(
                'SELECT id, val FROM '.$tbl.' WHERE val_hash = ? ORDER BY id',
                [$this->getValueHash($val)]
            );
            if (\is_array($rows) && 0 < \count($rows)) {
                foreach ($rows as $row) {
                    if ($row['val'] == $val) {
                        $r = $row['id'];
                        break;
                    }
                }
            }
        } else {
            /* exact match */
            $sql = 'SELECT id FROM '.$tbl.' WHERE val = ? LIMIT 1';
            $row = $this->store->getDBObject()->fetchRow($sql, [$val]);

            if (null !== $row && isset($row['id'])) {
                $r = $row['id'];
            }
        }
        if ($r && (\strlen($val) < 100)) {
            $this->term_id_cache[$term][$val] = $r;
        }

        return $r;
    }

    public function getValueHash(int | float | string $val): int | float
    {
        return abs(crc32($val));
    }
}

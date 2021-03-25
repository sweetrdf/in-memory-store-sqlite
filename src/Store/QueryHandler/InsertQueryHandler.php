<?php

/*
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-3 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Benjamin Nowak
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sweetrdf\InMemoryStoreSqlite\Store\QueryHandler;

use function sweetrdf\InMemoryStoreSqlite\getNormalizedValue;
use sweetrdf\InMemoryStoreSqlite\KeyValueBag;
use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;

class InsertQueryHandler extends QueryHandler
{
    /**
     * If true, store assumes one or more insert into SPARQL queries and will
     * skip certain DB operations to speed up insertion process.
     */
    private bool $bulkLoadModeIsActive = false;

    /**
     * Is used if $bulkLoadModeIsActive is true. Determines next term ID for
     * entries in id2val, s2val and o2val.
     */
    private int $bulkLoadModeNextTermId = 1;

    /**
     * When set it is used to store term information to speed up insert into operations.
     */
    private KeyValueBag $rowCache;

    /**
     * Is being used for blank nodes to generate a hash which is not only dependent on
     * blank node ID and graph, but also on a random value.
     * Otherwise blank nodes inserted in different "insert-sessions" will have the same reference.
     */
    private ?string $sessionId = null;

    public function __construct(InMemoryStoreSqlite $store, Logger $logger)
    {
        parent::__construct($store, $logger);

        // default value; will be overridden when running inside InMemoryStoreSqlite
        $this->rowCache = new KeyValueBag();
    }

    public function activateBulkLoadMode(int $bulkLoadModeNextTermId): void
    {
        $this->bulkLoadModeIsActive = true;
        $this->bulkLoadModeNextTermId = $bulkLoadModeNextTermId;
    }

    public function getBulkLoadModeNextTermId(): int
    {
        return $this->bulkLoadModeNextTermId;
    }

    public function setRowCache(KeyValueBag $rowCache): void
    {
        $this->rowCache = $rowCache;
    }

    public function runQuery(array $infos)
    {
        $this->sessionId = bin2hex(random_bytes(4));
        $this->store->getDBObject()->getPDO()->beginTransaction();

        foreach ($infos['query']['construct_triples'] as $triple) {
            $this->addTripleToGraph($triple, $infos['query']['target_graph']);
        }

        $this->store->getDBObject()->getPDO()->commit();

        $this->sessionId = null;
    }

    private function addTripleToGraph(array $triple, string $graph): void
    {
        /*
         * information:
         *
         *  + val_hash: hashed version of given value
         *  + val_type: type of the term; one of: bnode, uri, literal
         */

        $triple = $this->prepareTriple($triple, $graph);

        /*
         * graph
         */
        $graphId = $this->getIdOfExistingTerm($graph, 'id');
        if (null == $graphId) {
            $graphId = $this->store->getDBObject()->insert('id2val', [
                'id' => $this->getMaxTermId(),
                'val' => $graph,
                'val_type' => 0, // = uri
            ]);
        }

        /*
         * s2val
         */
        $subjectId = $this->getIdOfExistingTerm($triple['s'], 'subject');
        if (null == $subjectId) {
            $subjectId = $this->getMaxTermId();
            $this->store->getDBObject()->insert('s2val', [
                'id' => $subjectId,
                'val' => $triple['s'],
                'val_hash' => $this->getValueHash($triple['s']),
            ]);
        }

        /*
         * predicate
         */
        $predicateId = $this->getIdOfExistingTerm($triple['p'], 'id');
        if (null == $predicateId) {
            $predicateId = $this->getMaxTermId();
            $this->store->getDBObject()->insert('id2val', [
                'id' => $predicateId,
                'val' => $triple['p'],
                'val_type' => 0, // = uri
            ]);
        }

        /*
         * o2val
         */
        $objectId = $this->getIdOfExistingTerm($triple['o'], 'object');
        if (null == $objectId) {
            $objectId = $this->getMaxTermId();
            $this->store->getDBObject()->insert('o2val', [
                'id' => $objectId,
                'val' => $triple['o'],
                'val_hash' => $this->getValueHash($triple['o']),
            ]);
        }

        /*
         * o_lang_dt
         */
        // notice: only one of these two is set
        $oLangDt = $triple['o_datatype'].$triple['o_lang'];
        $oLangDtId = $this->getIdOfExistingTerm($oLangDt, 'id');
        if (null == $oLangDtId) {
            $oLangDtId = $this->getMaxTermId();
            $this->store->getDBObject()->insert('id2val', [
                'id' => $oLangDtId,
                'val' => $oLangDt,
                'val_type' => !empty($triple['o_datatype']) ? 0 : 2,
            ]);
        }

        /*
         * triple
         */
        $sql = 'SELECT * FROM triple WHERE s = ? AND p = ? AND o = ?';
        $check = $this->store->getDBObject()->fetchRow($sql, [$subjectId, $predicateId, $objectId]);
        if (false === $check) {
            $tripleId = $this->store->getDBObject()->insert('triple', [
                's' => $subjectId,
                's_type' => $triple['s_type_int'],
                'p' => $predicateId,
                'o' => $objectId,
                'o_type' => $triple['o_type_int'],
                'o_lang_dt' => $oLangDtId,
                'o_comp' => getNormalizedValue($triple['o']),
            ]);
        } else {
            $tripleId = $check['t'];
        }

        /*
         * triple to graph
         */
        $sql = 'SELECT * FROM g2t WHERE g = ? AND t = ?';
        $check = $this->store->getDBObject()->fetchRow($sql, [$graphId, $tripleId]);
        if (false == $check) {
            $this->store->getDBObject()->insert('g2t', [
                'g' => $graphId,
                't' => $tripleId,
            ]);
        }
    }

    private function prepareTriple(array $triple, string $graph): array
    {
        /*
         * subject: set type int
         */
        $triple['s_type_int'] = 0; // uri
        if ('bnode' == $triple['s_type']) {
            $triple['s_type_int'] = 1;
        } elseif ('literal' == $triple['s_type']) {
            $triple['s_type_int'] = 2;
        }

        /*
         * subject is a blank node
         */
        if ('bnode' == $triple['s_type']) {
            // transforms _:foo to _:b671320391_foo
            $s = $triple['s'];
            // TODO make bnode ID only unique for this session, not in general
            $triple['s'] = '_:b'.$this->getValueHash($this->sessionId.$graph.$s).'_';
            $triple['s'] .= substr($s, 2);
        }

        /*
         * object: set type int
         */
        $triple['o_type_int'] = 0; // uri
        if ('bnode' == $triple['o_type']) {
            $triple['o_type_int'] = 1;
        } elseif ('literal' == $triple['o_type']) {
            $triple['o_type_int'] = 2;
        }

        /*
         * object is a blank node
         */
        if ('bnode' == $triple['o_type']) {
            // transforms _:foo to _:b671320391_foo
            $o = $triple['o'];
            // TODO make bnode ID only unique for this session, not in general
            $triple['o'] = '_:b'.$this->getValueHash($this->sessionId.$graph.$o).'_';
            $triple['o'] .= substr($o, 2);
        }

        return $triple;
    }

    /**
     * Generates the next valid ID based on latest values in id2val, s2val and o2val.
     *
     * @return int returns 1 or higher
     */
    private function getMaxTermId(): int
    {
        if (true === $this->bulkLoadModeIsActive) {
            return $this->bulkLoadModeNextTermId++;
        } else {
            $sql = '';
            foreach (['id2val', 's2val', 'o2val'] as $table) {
                $sql .= !empty($sql) ? ' UNION ' : '';
                $sql .= 'SELECT MAX(id) as id FROM '.$table;
            }
            $result = 0;

            $rows = $this->store->getDBObject()->fetchList($sql);

            if (\is_array($rows)) {
                foreach ($rows as $row) {
                    $result = ($result < $row['id']) ? $row['id'] : $result;
                }
            }

            return $result + 1;
        }
    }

    /**
     * @param string $type     One of: bnode, uri, literal
     * @param string $quadPart One of: id, subject, object
     *
     * @return int 1 (or higher), if available, or null
     */
    private function getIdOfExistingTerm(string $value, string $quadPart): ?int
    {
        // id (predicate or graph)
        if ('id' == $quadPart) {
            $sql = 'SELECT id, val FROM id2val WHERE val = ?';

            $hashKey = md5($sql.json_encode([$value]));
            if (false === $this->rowCache->has($hashKey)) {
                $row = $this->store->getDBObject()->fetchRow($sql, [$value]);
                if (\is_array($row)) {
                    $this->rowCache->set($hashKey, $row);
                }
            }

            $entry = $this->rowCache->get($hashKey);

            // entry found, use its ID
            if (\is_array($entry)) {
                return $entry['id'];
            } else {
                return null;
            }
        } else {
            // subject or object
            $table = 'subject' == $quadPart ? 's2val' : 'o2val';
            $sql = 'SELECT id, val FROM '.$table.' WHERE val_hash = ?';
            $params = [$this->getValueHash($value)];

            $hashKey = md5($sql.json_encode($params));
            if (false === $this->rowCache->has($hashKey)) {
                $row = $this->store->getDBObject()->fetchRow($sql, $params);
                if (\is_array($row)) {
                    $this->rowCache->set($hashKey, $row);
                }
            }

            $entry = $this->rowCache->get($hashKey);

            // entry found, use its ID
            if (isset($entry['val']) && $entry['val'] == $value) {
                return $entry['id'];
            } else {
                return null;
            }
        }
    }
}

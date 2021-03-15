<?php

namespace sweetrdf\InMemoryStoreSqlite\Store;

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

class InsertQueryHandler extends \ARC2_StoreQueryHandler
{
    public function runQuery(array $infos)
    {
        foreach ($infos['query']['construct_triples'] as $triple) {
            $this->addQuad($triple, $infos['query']['target_graph']);
        }
    }

    private function addQuad(array $triple, string $graph): void
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
                'val_hash' => $this->store->getValueHash($triple['s']),
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
                'val_hash' => $this->store->getValueHash($triple['o']),
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
                'o_comp' => $this->getOComp($triple['o']),
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
            $triple['s'] = '_:b'.$this->store->getValueHash($graph.$s).'_';
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
            $triple['o'] = '_:b'.$this->store->getValueHash($graph.$o).'_';
            $triple['o'] .= substr($o, 2);
        }

        return $triple;
    }

    /**
     * Get normalized value for ORDER BY operations.
     */
    private function getOComp($val): string
    {
        /* try date (e.g. 21 August 2007) */
        if (
            preg_match('/^[0-9]{1,2}\s+[a-z]+\s+[0-9]{4}/i', $val)
            && ($uts = strtotime($val))
            && (-1 !== $uts)
        ) {
            return date("Y-m-d\TH:i:s", $uts);
        }

        /* xsd date (e.g. 2009-05-28T18:03:38+09:00 2009-05-28T18:03:38GMT) */
        if (true === (bool) strtotime($val)) {
            return date('Y-m-d\TH:i:s\Z', strtotime($val));
        }

        if (is_numeric($val)) {
            $val = sprintf('%f', $val);
            if (preg_match("/([\-\+])([0-9]*)\.([0-9]*)/", $val, $m)) {
                return $m[1].sprintf('%018s', $m[2]).'.'.sprintf('%-015s', $m[3]);
            }
            if (preg_match("/([0-9]*)\.([0-9]*)/", $val, $m)) {
                return '+'.sprintf('%018s', $m[1]).'.'.sprintf('%-015s', $m[2]);
            }

            return $val;
        }

        /* any other string: remove tags, linebreaks etc., but keep MB-chars */
        // [\PL\s]+ ( = non-Letters) kills digits
        $re = '/[\PL\s]+/isu';
        $re = '/[\s\'\"\´\`]+/is';
        $val = trim(preg_replace($re, '-', strip_tags($val)));
        if (\strlen($val) > 35) {
            $fnc = \function_exists('mb_substr') ? 'mb_substr' : 'substr';
            $val = $fnc($val, 0, 17).'-'.$fnc($val, -17);
        }

        return $val;
    }

    /**
     * Generates the next valid ID based on latest values in id2val, s2val and o2val.
     *
     * @return int returns 1 or higher
     */
    private function getMaxTermId(): int
    {
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
            $entry = $this->store->getDBObject()->fetchRow($sql, [$value]);

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
            $params = [$this->store->getValueHash($value)];
            $entry = $this->store->getDBObject()->fetchRow($sql, $params);

            // entry found, use its ID
            if (isset($entry['val']) && $entry['val'] == $value) {
                return $entry['id'];
            } else {
                return null;
            }
        }
    }
}

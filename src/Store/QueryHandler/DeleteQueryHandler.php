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

namespace sweetrdf\InMemoryStoreSqlite\Store\QueryHandler;

use Exception;

class DeleteQueryHandler extends QueryHandler
{
    private bool $refs_deleted;

    public function runQuery($infos)
    {
        $this->infos = $infos;
        /* delete */
        $this->refs_deleted = false;
        /* graph(s) only */
        $constructTriples = $this->infos['query']['construct_triples'] ?? [];
        $pattern = $this->infos['query']['pattern'] ?? [];
        if (!$constructTriples) {
            $tc = $this->deleteTargetGraphs();
        } elseif (!$pattern) {
            /* graph(s) + explicit triples */
            $tc = $this->deleteTriples();
        } else {
            /* graph(s) + constructed triples */
            $tc = $this->deleteConstructedGraph();
        }
        /* clean up */
        if ($tc && ($this->refs_deleted || (1 == rand(1, 100)))) {
            $this->cleanTableReferences();
        }

        return [
            't_count' => $tc,
            'delete_time' => 0,
            'index_update_time' => 0,
        ];
    }

    private function deleteTargetGraphs()
    {
        $r = 0;
        foreach ($this->infos['query']['target_graphs'] as $g) {
            if ($g_id = $this->getTermID($g, 'g')) {
                $r += $this->store->getDBObject()->exec('DELETE FROM g2t WHERE g = '.$g_id);
            }
        }
        $this->refs_deleted = $r ? 1 : 0;

        return $r;
    }

    private function deleteTriples()
    {
        $r = 0;
        /* graph restriction */
        $tgs = $this->infos['query']['target_graphs'];
        $gq = '';
        foreach ($tgs as $g) {
            if ($g_id = $this->getTermID($g, 'g')) {
                $gq .= $gq ? ', '.$g_id : $g_id;
            }
        }
        $gq = $gq ? ' AND G.g IN ('.$gq.')' : '';
        /* triples */
        foreach ($this->infos['query']['construct_triples'] as $t) {
            $q = '';
            $skip = 0;
            foreach (['s', 'p', 'o'] as $term) {
                if (isset($t[$term.'_type']) && preg_match('/(var)/', $t[$term.'_type'])) {
                    //$skip = 1;
                } else {
                    $term_id = $this->getTermID($t[$term], $term);
                    $q .= ($q ? ' AND ' : '').'T.'.$term.'='.$term_id;
                    /* explicit lang/dt restricts the matching */
                    if ('o' == $term) {
                        $o_lang = $t['o_lang'] ?? '';
                        $o_lang_dt = $t['o_datatype'] ?? $o_lang;
                        if ($o_lang_dt) {
                            $q .= ($q ? ' AND ' : '').'T.o_lang_dt='.$this->getTermID($o_lang_dt, 'lang_dt');
                        }
                    }
                }
            }
            if ($skip) {
                continue;
            }
            if ($gq) {
                $sql = 'DELETE FROM g2t WHERE t IN (';
                $sql .= 'SELECT G.t FROM g2t G JOIN triple T ON T.t = G.t'.$gq.' WHERE '.$q;
                $sql .= ')';
            } else {/* triples only */
                // it contains things like "T.s", but we can't use a table alias
                // with SQLite when running DELETE queries.
                $q = str_replace('T.', '', $q);
                $sql = 'DELETE FROM triple WHERE '.$q;
            }
            $r += $this->store->getDBObject()->exec($sql);
            if (!empty($this->store->getDBObject()->getErrorMessage())) {
                // TODO deletable because never reachable?
                throw new Exception($this->store->getDBObject()->getErrorMessage().' in '.$sql);
            }
        }

        return $r;
    }

    private function deleteConstructedGraph()
    {
        $subLogger = $this->store->getLoggerPool()->createNewLogger('Construct');
        $h = new ConstructQueryHandler($this->store, $subLogger);

        $sub_r = $h->runQuery($this->infos);
        $triples = $this->getTriplesFromIndex($sub_r);
        $tgs = $this->infos['query']['target_graphs'];

        $this->infos = ['query' => ['construct_triples' => $triples, 'target_graphs' => $tgs]];

        return $this->deleteTriples();
    }

    private function getTriplesFromIndex(array $index): array
    {
        $r = [];
        foreach ($index as $s => $ps) {
            foreach ($ps as $p => $os) {
                foreach ($os as $o) {
                    $r[] = [
                        's' => $s,
                        'p' => $p,
                        'o' => $o['value'],
                        's_type' => preg_match('/^\_\:/', $s) ? 'bnode' : 'uri',
                        'o_type' => $o['type'],
                        'o_datatype' => isset($o['datatype']) ? $o['datatype'] : '',
                        'o_lang' => isset($o['lang']) ? $o['lang'] : '',
                    ];
                }
            }
        }

        return $r;
    }

    private function cleanTableReferences()
    {
        /* check for unconnected triples */
        $sql = 'SELECT T.t
            FROM triple T LEFT JOIN g2t G ON ( G.t = T.t )
            WHERE G.t IS NULL
            LIMIT 1';

        $numRows = $this->store->getDBObject()->getNumberOfRows($sql);

        if (0 < $numRows) {
            /* delete unconnected triples */
            $sql = 'DELETE FROM triple WHERE t IN (';
            $sql .= '   SELECT T.t
                            FROM triple T
                                LEFT JOIN g2t G ON G.t = T.t
                            WHERE G.t IS NULL';
            $sql .= ')';
            $this->store->getDBObject()->simpleQuery($sql);
        }
        /* check for unconnected graph refs */
        if ((1 == rand(1, 10))) {
            $sql = '
                SELECT G.g FROM g2t G LEFT JOIN triple T ON ( T.t = G.t )
                WHERE T.t IS NULL LIMIT 1
            ';
            if (0 < $this->store->getDBObject()->getNumberOfRows($sql)) {
                /* delete unconnected graph refs */
                $sql = 'DELETE G
                    FROM g2t G
                    LEFT JOIN triple T ON (T.t = G.t)
                    WHERE T.t IS NULL
                ';
                $this->store->getDBObject()->simpleQuery($sql);
            }
        }
    }
}

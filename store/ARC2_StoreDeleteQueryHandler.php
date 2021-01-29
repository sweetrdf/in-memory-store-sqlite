<?php

/*
 *  This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;

class ARC2_StoreDeleteQueryHandler extends ARC2_StoreQueryHandler
{
    public function __construct($a, &$caller)
    {/* caller has to be a store */
        parent::__construct($a, $caller);
    }

    public function __init()
    {
        parent::__init();
        $this->store = $this->caller;
        $this->handler_type = 'delete';
    }

    public function runQuery($infos)
    {
        $this->infos = $infos;
        /* delete */
        $this->refs_deleted = false;
        /* graph(s) only */
        if (!$this->v('construct_triples', [], $this->infos['query'])) {
            $tc = $this->deleteTargetGraphs();
        }
        /* graph(s) + explicit triples */
        elseif (!$this->v('pattern', [], $this->infos['query'])) {
            $tc = $this->deleteTriples();
        }
        /* graph(s) + constructed triples */
        else {
            $tc = $this->deleteConstructedGraph();
        }
        /* clean up */
        if ($tc && ($this->refs_deleted || (1 == rand(1, 100)))) {
            $this->cleanTableReferences();
        }
        // TODO What does this rand() call here? remove it and think about a cleaner way
        //      when to trigger cleanValueTables
        if ($tc && (1 == rand(1, 500))) {
            $this->cleanValueTables();
        }

        return [
            't_count' => $tc,
            'delete_time' => 0,
            'index_update_time' => 0,
        ];
    }

    public function deleteTargetGraphs()
    {
        $r = 0;
        foreach ($this->infos['query']['target_graphs'] as $g) {
            if ($g_id = $this->getTermID($g, 'g')) {
                $r += $this->store->a['db_object']->exec('DELETE FROM g2t WHERE g = '.$g_id);
            }
        }
        $this->refs_deleted = $r ? 1 : 0;

        return $r;
    }

    public function deleteTriples()
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
                        $o_lang = $this->v1('o_lang', '', $t);
                        $o_lang_dt = $this->v1('o_datatype', $o_lang, $t);
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
            $r += $this->store->a['db_object']->exec($sql);
            if (!empty($this->store->a['db_object']->getErrorMessage())) {
                // TODO deletable because never reachable?
                throw new Exception($this->store->a['db_object']->getErrorMessage().' in '.$sql);
            }
        }

        return $r;
    }

    public function deleteConstructedGraph()
    {
        $h = new ARC2_StoreConstructQueryHandler($this->a, $this->store);
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

    public function cleanTableReferences()
    {
        $dbv = $this->store->getDBVersion();
        /* check for unconnected triples */
        $sql = 'SELECT T.t
            FROM triple T LEFT JOIN g2t G ON ( G.t = T.t )
            WHERE G.t IS NULL
            LIMIT 1';

        $numRows = $this->store->a['db_object']->getNumberOfRows($sql);

        if (0 < $numRows) {
            /* delete unconnected triples */
            $sql = 'DELETE FROM triple WHERE t IN (';
            $sql .= '   SELECT T.t
                            FROM triple T
                                LEFT JOIN g2t G ON G.t = T.t
                            WHERE G.t IS NULL';
            $sql .= ')';
            $this->store->a['db_object']->simpleQuery($sql);
        }
        /* check for unconnected graph refs */
        if ((1 == rand(1, 10))) {
            $sql = '
                SELECT G.g FROM g2t G LEFT JOIN triple T ON ( T.t = G.t )
                WHERE T.t IS NULL LIMIT 1
            ';
            if (0 < $this->store->a['db_object']->getNumberOfRows($sql)) {
                /* delete unconnected graph refs */
                $sql = 'DELETE G
                    FROM g2t G
                    LEFT JOIN triple T ON (T.t = G.t)
                    WHERE T.t IS NULL
                ';
                $this->store->a['db_object']->simpleQuery($sql);
            }
        }
    }

    public function cleanValueTables()
    {
        $dbv = $this->store->getDBVersion();

        /* o2val */
        $sql = ($dbv < '04-01') ? 'DELETE o2val' : 'DELETE V';
        $sql .= '
      FROM o2val V
      LEFT JOIN triple T ON (T.o = V.id)
      WHERE T.t IS NULL
    ';
        $this->store->a['db_object']->simpleQuery($sql);

        /* s2val */
        $sql = ($dbv < '04-01') ? 'DELETE s2val' : 'DELETE V';
        $sql .= '
      FROM s2val V
      LEFT JOIN triple T ON (T.s = V.id)
      WHERE T.t IS NULL
    ';
        $this->store->a['db_object']->simpleQuery($sql);

        /* id2val */
        $sql = ($dbv < '04-01') ? 'DELETE id2val' : 'DELETE V';
        $sql .= '
      FROM id2val V
      LEFT JOIN g2t G ON (G.g = V.id)
      LEFT JOIN triple T1 ON (T1.p = V.id)
      LEFT JOIN triple T2 ON (T2.o_lang_dt = V.id)
      WHERE G.g IS NULL AND T1.t IS NULL AND T2.t IS NULL
    ';
        // TODO was commented out before. could this be a problem?
        $this->store->a['db_object']->simpleQuery($sql);
    }
}

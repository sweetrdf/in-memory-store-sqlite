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

class ConstructQueryHandler extends SelectQueryHandler
{
    public function runQuery($infos)
    {
        $this->infos = $infos;
        $this->buildResultVars();
        $this->infos['query']['distinct'] = 1;
        $sub_r = parent::runQuery($this->infos);
        $rf = $infos['result_format'] ?? '';
        if (\in_array($rf, ['sql', 'structure', 'index'])) {
            return $sub_r;
        }

        return $this->getResultIndex($sub_r);
    }

    private function buildResultVars()
    {
        $r = [];
        foreach ($this->infos['query']['construct_triples'] as $t) {
            foreach (['s', 'p', 'o'] as $term) {
                if ('var' == $t[$term.'_type']) {
                    if (!\in_array($t[$term], $r)) {
                        $r[] = ['var' => $t[$term], 'aggregate' => '', 'alias' => ''];
                    }
                }
            }
        }
        $this->infos['query']['result_vars'] = $r;
    }

    private function getResultIndex($qr)
    {
        $r = [];
        $added = [];
        $rows = $qr['rows'] ?? [];
        $cts = $this->infos['query']['construct_triples'];
        $bnc = 0;
        foreach ($rows as $row) {
            ++$bnc;
            foreach ($cts as $ct) {
                $skip_t = 0;
                $t = [];
                foreach (['s', 'p', 'o'] as $term) {
                    $val = $ct[$term];
                    $type = $ct[$term.'_type'];
                    $val = ('bnode' == $type) ? $val.$bnc : $val;
                    if ('var' == $type) {
                        $skip_t = !isset($row[$val]) ? 1 : $skip_t;
                        $type = !$skip_t ? $row[$val.' type'] : '';
                        $val = (!$skip_t) ? $row[$val] : '';
                    }
                    $t[$term] = $val;
                    $t[$term.'_type'] = $type;
                    if (isset($row[$ct[$term].' lang'])) {
                        $t[$term.'_lang'] = $row[$ct[$term].' lang'];
                    }
                    if (isset($row[$ct[$term].' datatype'])) {
                        $t[$term.'_datatype'] = $row[$ct[$term].' datatype'];
                    }
                }
                if (!$skip_t) {
                    $s = $t['s'];
                    $p = $t['p'];
                    $o = $t['o'];
                    if (!isset($r[$s])) {
                        $r[$s] = [];
                    }
                    if (!isset($r[$s][$p])) {
                        $r[$s][$p] = [];
                    }
                    $o = ['value' => $o];
                    foreach (['lang', 'type', 'datatype'] as $suffix) {
                        if (isset($t['o_'.$suffix]) && $t['o_'.$suffix]) {
                            $o[$suffix] = $t['o_'.$suffix];
                        }
                    }
                    if (!isset($added[md5($s.' '.$p.' '.serialize($o))])) {
                        $r[$s][$p][] = $o;
                        $added[md5($s.' '.$p.' '.serialize($o))] = 1;
                    }
                }
            }
        }

        return $r;
    }
}

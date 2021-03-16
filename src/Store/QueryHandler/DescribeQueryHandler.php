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

class DescribeQueryHandler extends SelectQueryHandler
{
    public function runQuery($infos)
    {
        $ids = $infos['query']['result_uris'];
        if ($vars = $infos['query']['result_vars']) {
            $sub_r = parent::runQuery($infos);
            $rf = $infos['result_format'] ?? '';
            if (\in_array($rf, ['sql', 'structure', 'index'])) {
                return $sub_r;
            }
            $rows = $sub_r['rows'] ?? [];
            foreach ($rows as $row) {
                foreach ($vars as $info) {
                    $val = isset($row[$info['var']]) ? $row[$info['var']] : '';
                    if (
                        $val
                        && ('literal' != $row[$info['var'].' type']) && !\in_array($val, $ids)
                    ) {
                        $ids[] = $val;
                    }
                }
            }
        }
        $this->r = [];
        $this->described_ids = [];
        $this->ids = $ids;
        $this->added_triples = [];
        $is_sub_describe = 0;
        while ($this->ids) {
            $id = $this->ids[0];
            $this->described_ids[] = $id;
            $q = 'CONSTRUCT { <'.$id.'> ?p ?o . } WHERE {<'.$id.'> ?p ?o .}';
            $sub_r = $this->store->query($q);
            $sub_index = \is_array($sub_r['result']) ? $sub_r['result'] : [];
            $this->mergeSubResults($sub_index, $is_sub_describe);
            $is_sub_describe = 1;
        }

        return $this->r;
    }

    public function mergeSubResults($index, $is_sub_describe = 1)
    {
        foreach ($index as $s => $ps) {
            if (!isset($this->r[$s])) {
                $this->r[$s] = [];
            }
            foreach ($ps as $p => $os) {
                if (!isset($this->r[$s][$p])) {
                    $this->r[$s][$p] = [];
                }
                foreach ($os as $o) {
                    $id = md5($s.' '.$p.' '.serialize($o));
                    if (!isset($this->added_triples[$id])) {
                        if (1 || !$is_sub_describe) {
                            $this->r[$s][$p][] = $o;
                            if (\is_array($o) && ('bnode' == $o['type']) && !\in_array($o['value'], $this->ids)) {
                                $this->ids[] = $o['value'];
                            }
                        } elseif (!\is_array($o) || ('bnode' != $o['type'])) {
                            $this->r[$s][$p][] = $o;
                        }
                        $this->added_triples[$id] = 1;
                    }
                }
            }
        }
        /* adjust ids */
        $ids = $this->ids;
        $this->ids = [];
        foreach ($ids as $id) {
            if (!\in_array($id, $this->described_ids)) {
                $this->ids[] = $id;
            }
        }
    }
}

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

namespace sweetrdf\InMemoryStoreSqlite\Parser;

class SPARQLPlusParser extends SPARQLParser
{
    /* +1 */

    protected function xQuery($v)
    {
        list($r, $v) = $this->xPrologue($v);
        foreach (['Select', 'Construct', 'Describe', 'Ask', 'Insert', 'Delete', 'Load'] as $type) {
            $m = 'x'.$type.'Query';
            if ((list($r, $v) = $this->$m($v)) && $r) {
                return [$r, $v];
            }
        }

        return [0, $v];
    }

    /* +3 */

    protected function xResultVar($v)
    {
        $aggregate = '';
        /* aggregate */
        if ($sub_r = $this->x('\(?(AVG|COUNT|MAX|MIN|SUM)\s*\(\s*([^\)]+)\)\s+AS\s+([^\s\)]+)\)?', $v)) {
            $aggregate = $sub_r[1];
            $result_var = $sub_r[3];
            $v = $sub_r[2].$sub_r[4];
        }
        if ($sub_r && (list($sub_r, $sub_v) = $this->xVar($result_var)) && $sub_r) {
            $result_var = $sub_r['value'];
        }
        /* * or var */
        if ((list($sub_r, $sub_v) = $this->x('\*', $v)) && $sub_r) {
            return [['var' => '*', 'aggregate' => $aggregate, 'alias' => $aggregate ? $result_var : ''], $sub_v];
        }
        if ((list($sub_r, $sub_v) = $this->xVar($v)) && $sub_r) {
            return [['var' => $sub_r['value'], 'aggregate' => $aggregate, 'alias' => $aggregate ? $result_var : ''], $sub_v];
        }

        return [0, $v];
    }

    /* +4 */

    protected function xLoadQuery($v)
    {
        if ($sub_r = $this->x('LOAD\s+', $v)) {
            $sub_v = $sub_r[1];
            if ((list($sub_r, $sub_v) = $this->xIRIref($sub_v)) && $sub_r) {
                $r = ['type' => 'load', 'url' => $sub_r, 'target_graph' => ''];

                return [$r, $sub_v];
            }
        }

        return [0, $v];
    }

    /* +5 */

    protected function xInsertQuery($v)
    {
        if ($sub_r = $this->x('INSERT\s+', $v)) {
            $r = [
                'type' => 'insert',
                'dataset' => [],
            ];
            $sub_v = $sub_r[1];
            /* target */
            if ($sub_r = $this->x('INTO\s+', $sub_v)) {
                $sub_v = $sub_r[1];
                if ((list($sub_r, $sub_v) = $this->xIRIref($sub_v)) && $sub_r) {
                    $r['target_graph'] = $sub_r;
                    /* CONSTRUCT keyword, optional */
                    if ($sub_r = $this->x('CONSTRUCT\s+', $sub_v)) {
                        $sub_v = $sub_r[1];
                    }
                    /* construct template */
                    if ((list($sub_r, $sub_v) = $this->xConstructTemplate($sub_v)) && \is_array($sub_r)) {
                        $r['construct_triples'] = $sub_r;
                    } else {
                        $this->logger->error('Construct Template not found');

                        return [0, $v];
                    }
                    /* dataset */
                    while ((list($sub_r, $sub_v) = $this->xDatasetClause($sub_v)) && $sub_r) {
                        $r['dataset'][] = $sub_r;
                    }
                    /* where */
                    if ((list($sub_r, $sub_v) = $this->xWhereClause($sub_v)) && $sub_r) {
                        $r['pattern'] = $sub_r;
                    }
                    /* solution modifier */
                    if ((list($sub_r, $sub_v) = $this->xSolutionModifier($sub_v)) && $sub_r) {
                        $r = array_merge($r, $sub_r);
                    }

                    return [$r, $sub_v];
                }
            }
        }

        return [0, $v];
    }

    /* +6 */

    protected function xDeleteQuery($v): array
    {
        if ($sub_r = $this->x('DELETE\s+', $v)) {
            $r = [
                'type' => 'delete',
                'target_graphs' => [],
            ];
            $sub_v = $sub_r[1];
            /* target */
            do {
                $proceed = false;
                if ($sub_r = $this->x('FROM\s+', $sub_v)) {
                    $sub_v = $sub_r[1];
                    if ((list($sub_r, $sub_v) = $this->xIRIref($sub_v)) && $sub_r) {
                        $r['target_graphs'][] = $sub_r;
                        $proceed = 1;
                    }
                }
            } while ($proceed);
            /* CONSTRUCT keyword, optional */
            if ($sub_r = $this->x('CONSTRUCT\s+', $sub_v)) {
                $sub_v = $sub_r[1];
            }
            /* construct template */
            if ((list($sub_r, $sub_v) = $this->xConstructTemplate($sub_v)) && \is_array($sub_r)) {
                $r['construct_triples'] = $sub_r;
                /* dataset */
                while ((list($sub_r, $sub_v) = $this->xDatasetClause($sub_v)) && $sub_r) {
                    $r['dataset'][] = $sub_r;
                }
                /* where */
                if ((list($sub_r, $sub_v) = $this->xWhereClause($sub_v)) && $sub_r) {
                    $r['pattern'] = $sub_r;
                }
                /* solution modifier */
                if ((list($sub_r, $sub_v) = $this->xSolutionModifier($sub_v)) && $sub_r) {
                    $r = array_merge($r, $sub_r);
                }
            }

            return [$r, $sub_v];
        }

        return [0, $v];
    }

    /* +7 */

    protected function xSolutionModifier($v): array
    {
        $r = [];
        if ((list($sub_r, $sub_v) = $this->xGroupClause($v)) && $sub_r) {
            $r['group_infos'] = $sub_r;
        }
        if ((list($sub_r, $sub_v) = $this->xOrderClause($sub_v)) && $sub_r) {
            $r['order_infos'] = $sub_r;
        }
        while ((list($sub_r, $sub_v) = $this->xLimitOrOffsetClause($sub_v)) && $sub_r) {
            $r = array_merge($r, $sub_r);
        }

        return ($v == $sub_v) ? [0, $v] : [$r, $sub_v];
    }

    /* +8 */

    protected function xGroupClause($v): array
    {
        if ($sub_r = $this->x('GROUP BY\s+', $v)) {
            $sub_v = $sub_r[1];
            $r = [];
            do {
                $proceed = 0;
                if ((list($sub_r, $sub_v) = $this->xVar($sub_v)) && $sub_r) {
                    $r[] = $sub_r;
                    $proceed = 1;
                    if ($sub_r = $this->x('\,', $sub_v)) {
                        $sub_v = $sub_r[1];
                    }
                }
            } while ($proceed);
            if (\count($r)) {
                return [$r, $sub_v];
            } else {
                $this->logger->error('No columns specified in GROUP BY clause.');
            }
        }

        return [0, $v];
    }
}

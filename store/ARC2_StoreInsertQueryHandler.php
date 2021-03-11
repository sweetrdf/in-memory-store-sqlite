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

class ARC2_StoreInsertQueryHandler extends ARC2_StoreQueryHandler
{
    /**
     * @todo move to parent
     */
    public function __construct(ARC2_Store $store)
    {
        $this->store = $store;
    }

    public function runQuery($infos, $keep_bnode_ids = 0)
    {
        $this->infos = $infos;
        /* insert */
        if (!$this->v('pattern', [], $this->infos['query'])) {
            $triples = $this->infos['query']['construct_triples'];
            /* don't execute empty INSERTs as they trigger a LOAD on the graph URI */
            if ($triples) {
                return $this->store->insert(
                    $triples,
                    $this->infos['query']['target_graph'],
                    $keep_bnode_ids
                );
            } else {
                return ['t_count' => 0, 'load_time' => 0];
            }
        } else {
            $keep_bnode_ids = 1;
            $h = new ARC2_StoreConstructQueryHandler($this->store);
            $sub_r = $h->runQuery($this->infos);
            if ($sub_r) {
                return $this->store->insert(
                    $sub_r,
                    $this->infos['query']['target_graph'],
                    $keep_bnode_ids
                );
            }

            return ['t_count' => 0, 'load_time' => 0];
        }
    }
}

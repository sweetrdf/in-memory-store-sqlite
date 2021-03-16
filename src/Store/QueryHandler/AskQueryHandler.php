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

class AskQueryHandler extends SelectQueryHandler
{
    public function runQuery($infos)
    {
        $infos['query']['limit'] = 1;
        $this->infos = $infos;
        $this->buildResultVars();

        return parent::runQuery($this->infos);
    }

    public function buildResultVars()
    {
        $this->infos['query']['result_vars'][] = [
            'var' => '1',
            'aggregate' => '',
            'alias' => 'success',
        ];
    }

    public function getFinalQueryResult($q_sql, $tmp_tbl)
    {
        $row = $this->store->getDBObject()->fetchRow('SELECT success FROM '.$tmp_tbl);
        $r = isset($row['success']) ? $row['success'] : 0;

        return $r ? true : false;
    }
}

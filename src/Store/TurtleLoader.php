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

namespace sweetrdf\InMemoryStoreSqlite\Store;

use sweetrdf\InMemoryStoreSqlite\Parser\TurtleParser;
use sweetrdf\InMemoryStoreSqlite\Store\QueryHandler\LoadQueryHandler;

class TurtleLoader extends TurtleParser
{
    private LoadQueryHandler $caller;

    public function setCaller(LoadQueryHandler $caller): void
    {
        $this->caller = $caller;
    }

    public function addT(array $t): void
    {
        $this->caller->addT(
            $t['s'],
            $t['p'],
            $t['o'],
            $t['s_type'],
            $t['o_type'],
            $t['o_datatype'],
            $t['o_lang']
        );

        ++$this->t_count;
    }
}

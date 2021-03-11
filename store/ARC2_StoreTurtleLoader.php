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

class ARC2_StoreTurtleLoader extends ARC2_TurtleParser
{
    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);
    }

    public function addT($t)
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

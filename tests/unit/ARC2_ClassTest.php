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

class ARC2_ClassTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $array = [];
        $stdClass = new stdClass();
        $this->arc2 = new ARC2_Class($array, $stdClass);
    }

    public function testV()
    {
        $this->assertFalse($this->arc2->v(null));
        $this->assertFalse($this->arc2->v('cats', false, []));
        $this->assertTrue($this->arc2->v('cats', false, ['cats' => true]));

        $o = new stdclass();
        $o->cats = true;
        $this->assertTrue($this->arc2->v('cats', false, $o));
    }

    public function testV1()
    {
        $this->assertFalse($this->arc2->v1(null));
        $this->assertFalse($this->arc2->v1('cats', false, []));
        $this->assertTrue($this->arc2->v1('cats', false, ['cats' => true]));
        $this->assertSame('blackjack', $this->arc2->v1('cats', 'blackjack', ['cats' => null]));

        $o = new stdclass();
        $o->cats = true;
        $this->assertTrue($this->arc2->v1('cats', false, $o));

        $o = new stdclass();
        $o->cats = 0;
        $this->assertSame('blackjack', $this->arc2->v1('cats', 'blackjack', $o));
    }
}

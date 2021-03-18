<?php

/*
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-3 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integration;

use sweetrdf\InMemoryStoreSqlite\KeyValueBag;
use Tests\TestCase;

class KeyValueBagTest extends TestCase
{
    private function getSubjectUnderTest(): KeyValueBag
    {
        return new KeyValueBag();
    }

    public function testGetSetHasEntries()
    {
        $sut = $this->getSubjectUnderTest();

        $this->assertFalse($sut->hasEntries());

        $sut->set('foo', [1]);

        $this->assertEquals([1], $sut->get('foo'));
        $this->assertTrue($sut->hasEntries());
    }

    public function testReset()
    {
        $sut = $this->getSubjectUnderTest();

        $sut->set('foo', ['bar']);

        $this->assertTrue($sut->hasEntries());

        $sut->reset();

        $this->assertFalse($sut->hasEntries());
    }
}

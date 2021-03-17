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

namespace Tests\Unit;

use Exception;
use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use Tests\TestCase;

class LoggerTest extends TestCase
{
    private function getSubjectUnderTest(): Logger
    {
        return new Logger();
    }

    public function testGetEntriesLevelNotSet()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Level invalid not set');

        $this->getSubjectUnderTest()->getEntries('invalid');
    }

    public function testGetEntries()
    {
        $sut = $this->getSubjectUnderTest();

        $sut->error('error1');
        $sut->warning('warning1');

        $this->assertEquals(2, \count($sut->getEntries()));
        $this->assertEquals(1, \count($sut->getEntries('error')));
        $this->assertEquals(1, \count($sut->getEntries('warning')));
    }

    public function testHasEntriesLevelNotSet()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Level invalid not set');

        $this->getSubjectUnderTest()->hasEntries('invalid');
    }

    public function testHasEntries()
    {
        $sut = $this->getSubjectUnderTest();

        $sut->error('error1');
        $sut->warning('warning1');

        $this->assertTrue($sut->hasEntries('error'));
        $this->assertTrue($sut->hasEntries('warning'));
        $this->assertTrue($sut->hasEntries());
    }
}

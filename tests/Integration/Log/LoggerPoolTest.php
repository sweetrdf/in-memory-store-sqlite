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

namespace Tests\Unit\Log;

use Exception;
use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use sweetrdf\InMemoryStoreSqlite\Log\LoggerPool;
use Tests\TestCase;

class LoggerPoolTest extends TestCase
{
    private function getSubjectUnderTest(): LoggerPool
    {
        return new LoggerPool();
    }

    public function testCreateNewLogger()
    {
        $this->assertEquals(new Logger(), $this->getSubjectUnderTest()->createNewLogger('test'));
    }

    public function testGetLoggerInvalid()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ID given.');

        $this->getSubjectUnderTest()->getLogger('test');
    }

    public function testGetLogger()
    {
        $sut = $this->getSubjectUnderTest();
        $logger = $sut->createNewLogger('test');

        $this->assertEquals(new Logger(), $logger);
        $this->assertEquals($logger, $sut->getLogger('test'));
    }

    public function testGetEntriesFromAllLoggerInstancesInvalid()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Level invalid not set');

        $sut = $this->getSubjectUnderTest();
        $sut->createNewLogger('1');

        $sut->getEntriesFromAllLoggerInstances('invalid');
    }

    public function testGetEntriesFromAllLoggerInstances()
    {
        $sut = $this->getSubjectUnderTest();

        $logger1 = $sut->createNewLogger('1');
        $logger1->error('error1');

        $logger2 = $sut->createNewLogger('2');
        $logger2->warning('warning1');

        $this->assertEquals(2, \count($sut->getEntriesFromAllLoggerInstances()));
        $this->assertEquals(1, \count($sut->getEntriesFromAllLoggerInstances('error')));
        $this->assertEquals(1, \count($sut->getEntriesFromAllLoggerInstances('warning')));
    }

    public function testHasEntriesInAnyLoggerInstanceInvalid()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Level invalid not set');

        $sut = $this->getSubjectUnderTest();
        $sut->createNewLogger('1');

        $sut->hasEntriesInAnyLoggerInstance('invalid');
    }

    public function testHasEntriesInAnyLoggerInstance()
    {
        $sut = $this->getSubjectUnderTest();

        $sut->createNewLogger('1')->error('error1');
        $sut->createNewLogger('2')->warning('warning1');

        $this->assertTrue($sut->hasEntriesInAnyLoggerInstance());
        $this->assertTrue($sut->hasEntriesInAnyLoggerInstance('error'));
        $this->assertTrue($sut->hasEntriesInAnyLoggerInstance('warning'));
    }
}

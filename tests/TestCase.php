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

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    /**
     * Subject under test.
     *
     * @var mixed
     */
    protected $fixture;

    protected string $rootPath = __DIR__;
}

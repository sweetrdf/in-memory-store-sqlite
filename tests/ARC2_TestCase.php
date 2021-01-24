<?php

/*
 *  This file is part of the quickrdf/InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class ARC2_TestCase extends TestCase
{
    /**
     * Store configuration to connect with the database.
     *
     * @var array
     */
    protected $dbConfig;

    /**
     * Subject under test.
     *
     * @var mixed
     */
    protected $fixture;

    protected function setUp(): void
    {
        global $dbConfig;

        $this->dbConfig = $dbConfig;

        // in case we run with a cache, clear it
        if (
            isset($this->dbConfig['cache_instance'])
            && $this->dbConfig['cache_instance'] instanceof CacheInterface
        ) {
            $this->dbConfig['cache_instance']->clear();
        }
    }

    protected function tearDown(): void
    {
        // in case we run with a cache, clear it
        if (
            isset($this->dbConfig['cache_instance'])
            && $this->dbConfig['cache_instance'] instanceof CacheInterface
        ) {
            $this->dbConfig['cache_instance']->clear();
        }

        parent::tearDown();
    }
}

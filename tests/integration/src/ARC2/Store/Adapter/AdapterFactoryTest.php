<?php

namespace Tests\integration\src\ARC2\Store\Adapter;

use ARC2\Store\Adapter\AbstractAdapter;
use ARC2\Store\Adapter\AdapterFactory;
use Exception;
use Tests\ARC2_TestCase;

class AdapterFactoryTest extends ARC2_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new AdapterFactory();
    }

    /*
     * Tests for getInstanceFor
     */

    public function testGetInstanceFor()
    {
        // PDO (sqlite)
        $instance = $this->fixture->getInstanceFor('pdo', ['db_pdo_protocol' => 'sqlite']);
        $this->assertTrue($instance instanceof AbstractAdapter);
    }

    /*
     * Tests for getSupportedAdapters
     */

    public function testGetSupportedAdapters()
    {
        $this->assertEquals(['mysqli', 'pdo'], $this->fixture->getSupportedAdapters());
    }
}

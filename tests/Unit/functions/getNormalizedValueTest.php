<?php

namespace Tests\Unit\functions;

use function sweetrdf\InMemoryStoreSqlite\getNormalizedValue;
use Tests\TestCase;

class getNormalizedValueTest extends TestCase
{
    /**
     * Tests to behavior, if a datetime string was given.
     */
    public function test()
    {
        // case with +hourse
        $string = '2009-05-28T18:03:38+09:00';
        $this->assertEquals('2009-05-28T09:03:38Z', getNormalizedValue($string));

        // GMT case
        $string = '2009-05-28T18:03:38GMT';
        $this->assertEquals('2009-05-28T18:03:38Z', getNormalizedValue($string));
    }
}

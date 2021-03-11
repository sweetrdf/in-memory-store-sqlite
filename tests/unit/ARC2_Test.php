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

namespace Tests\unit;

use Tests\ARC2_TestCase;

class ARC2_Test extends ARC2_TestCase
{
    public function testX()
    {
        $actual = \ARC2::x('foo', '  foobar');
        $this->assertEquals('bar', $actual[1]);
    }

    public function testSplitURI()
    {
        $actual = \ARC2::splitURI('http://www.w3.org/XML/1998/namespacefoo');
        $this->assertEquals(['http://www.w3.org/XML/1998/namespace', 'foo'], $actual);

        $actual = \ARC2::splitURI('http://www.w3.org/2005/Atomfoo');
        $this->assertEquals(['http://www.w3.org/2005/Atom', 'foo'], $actual);

        $actual = \ARC2::splitURI('http://www.w3.org/2005/Atom#foo');
        $this->assertEquals(['http://www.w3.org/2005/Atom#', 'foo'], $actual);

        $actual = \ARC2::splitURI('http://www.w3.org/1999/xhtmlfoo');
        $this->assertEquals(['http://www.w3.org/1999/xhtml', 'foo'], $actual);

        $actual = \ARC2::splitURI('http://www.w3.org/1999/02/22-rdf-syntax-ns#foo');
        $this->assertEquals(['http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'foo'], $actual);

        $actual = \ARC2::splitURI('http://example.com/foo');
        $this->assertEquals(['http://example.com/', 'foo'], $actual);

        $actual = \ARC2::splitURI('http://example.com/foo/bar');
        $this->assertEquals(['http://example.com/foo/', 'bar'], $actual);

        $actual = \ARC2::splitURI('http://example.com/foo#bar');
        $this->assertEquals(['http://example.com/foo#', 'bar'], $actual);
    }
}

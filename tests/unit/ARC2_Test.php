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

namespace Tests\unit;

use Tests\ARC2_TestCase;

class ARC2_Test extends ARC2_TestCase
{
    public function testGetVersion()
    {
        $actual = \ARC2::getVersion();
        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/', $actual, 'should start with date');
    }

    public function testGetIncPath()
    {
        $actual = \ARC2::getIncPath('RDFParser');
        $this->assertStringEndsWith('parsers/', $actual, 'should create correct path');
        $this->assertTrue(is_dir($actual), 'should create correct pointer');
    }

    public function testGetScriptURI()
    {
        $tmp = $_SERVER;
        unset($_SERVER);
        $actual = \ARC2::getScriptURI();
        $this->assertEquals('http://localhost/unknown_path', $actual);
        $_SERVER = $tmp;

        $_SERVER = [
            'SERVER_PROTOCOL' => 'http',
            'SERVER_PORT' => 443,
            'HTTP_HOST' => 'example.com',
            'SCRIPT_NAME' => '/foo',
        ];
        $actual = \ARC2::getScriptURI();
        $this->assertEquals('https://example.com/foo', $actual);
        $_SERVER = $tmp;

        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SERVER_NAME']);
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $actual = \ARC2::getScriptURI();
        $this->assertEquals('file://'.__FILE__, $actual);
        $_SERVER = $tmp;
    }

    public function testGetRequestURI()
    {
        $tmp = $_SERVER;
        unset($_SERVER);
        $actual = \ARC2::getRequestURI();
        $this->assertEquals(\ARC2::getScriptURI(), $actual);
        $_SERVER = $tmp;

        $_SERVER = [
            'SERVER_PROTOCOL' => 'http',
            'SERVER_PORT' => 1234,
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/foo',
        ];
        $actual = \ARC2::getRequestURI();
        $this->assertEquals('http://example.com:1234/foo', $actual);
        $_SERVER = $tmp;
    }

    public function testMtime()
    {
        $actual = \ARC2::mtime();
        $this->assertTrue(\is_float($actual));
    }

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

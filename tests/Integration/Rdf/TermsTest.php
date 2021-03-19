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

use rdfInterface\DataFactory as iDataFactory;
use rdfInterface\tests\TermsTest as _TermsTest;
use sweetrdf\InMemoryStoreSqlite\Rdf\DataFactory;

class TermsTest extends _TermsTest
{
    public static function getDataFactory(): iDataFactory
    {
        return new DataFactory();
    }

    public function testLiteralFactory(): void
    {
        $this->markTestSkipped('Skipped for now. Wait until feedback/merge of https://github.com/sweetrdf/rdfInterface/pull/17.');
    }

    public function testLiteralWith(): void
    {
        $this->markTestSkipped('Function to test not implemented yet.');
    }

    public function testQuadWith(): void
    {
        $this->markTestSkipped('Function to test not implemented yet.');
    }

    public function testQuadTemplate(): void
    {
        $this->markTestSkipped('Function to test not implemented yet.');
    }

    public function testQuadTemplateQuad(): void
    {
        $this->markTestSkipped('Function to test not implemented yet.');
    }

    public function testQuadTemplateWith(): void
    {
        $this->markTestSkipped('Function to test not implemented yet.');
    }

    public function testQuadTemplateExceptions(): void
    {
        $this->markTestSkipped('Function to test not implemented yet.');
    }
}

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

namespace Tests\Integration\Parser;

use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\Parser\TurtleParser;
use sweetrdf\InMemoryStoreSqlite\StringReader;
use Tests\TestCase;

class TurtleParserTest extends TestCase
{
    private function getSubjectUnderTest(): TurtleParser
    {
        return new TurtleParser(new Logger(), new NamespaceHelper(), new StringReader());
    }

    /**
     * Demonstrates that parsing decimals works.
     *
     * @see https://github.com/sweetrdf/in-memory-store-sqlite/issues/8
     */
    public function testParseDecimals()
    {
        $sut = $this->getSubjectUnderTest();

        $data = '@prefix : <http://ex/> .
        :decimals :dec 1.0, 2.2, 0.1 .';

        $sut->parse('http://', $data);

        $this->assertEquals(
            [
                [
                    'type' => 'triple',
                    's' => 'http://ex/decimals',
                    's_type' => 'uri',
                    'p' => 'http://ex/dec',
                    'p_type' => 'uri',
                    'o' => '1.0',
                    'o_type' => 'literal',
                    'o_datatype' => 'http://www.w3.org/2001/XMLSchema#decimal',
                    'o_lang' => '',
                ],
                [
                    'type' => 'triple',
                    's' => 'http://ex/decimals',
                    's_type' => 'uri',
                    'p' => 'http://ex/dec',
                    'p_type' => 'uri',
                    'o' => '2.2',
                    'o_type' => 'literal',
                    'o_datatype' => 'http://www.w3.org/2001/XMLSchema#decimal',
                    'o_lang' => '',
                ],
                [
                    'type' => 'triple',
                    's' => 'http://ex/decimals',
                    's_type' => 'uri',
                    'p' => 'http://ex/dec',
                    'p_type' => 'uri',
                    'o' => '0.1',
                    'o_type' => 'literal',
                    'o_datatype' => 'http://www.w3.org/2001/XMLSchema#decimal',
                    'o_lang' => '',
                ],
            ],
            $sut->getTriples()
        );
    }

    /**
     * Test reusing StringReader instance.
     */
    public function testParseReusingStringReader()
    {
        $sut = $this->getSubjectUnderTest();

        /*
         * first run
         */
        $data = '@prefix : <http://ex/> .
        :decimals :dec 1.0, 2.2, 0.1 .';

        $sut->parse('http://', $data);

        $this->assertEquals(
            [
                'type' => 'triple',
                's' => 'http://ex/decimals',
                's_type' => 'uri',
                'p' => 'http://ex/dec',
                'p_type' => 'uri',
                'o' => '1.0',
                'o_type' => 'literal',
                'o_datatype' => 'http://www.w3.org/2001/XMLSchema#decimal',
                'o_lang' => '',
            ],
            $sut->getTriples()[0]
        );

        /*
         * second run
         */
        $data = '@prefix : <http://ex/> .
        :decimals :dec "foo", 0.42 .';

        $sut->parse('http://', $data);

        $this->assertEquals(
            [
                [
                    'type' => 'triple',
                    's' => 'http://ex/decimals',
                    's_type' => 'uri',
                    'p' => 'http://ex/dec',
                    'p_type' => 'uri',
                    'o' => 'foo',
                    'o_type' => 'literal',
                    'o_datatype' => '',
                    'o_lang' => '',
                ],
                [
                    'type' => 'triple',
                    's' => 'http://ex/decimals',
                    's_type' => 'uri',
                    'p' => 'http://ex/dec',
                    'p_type' => 'uri',
                    'o' => '0.42',
                    'o_type' => 'literal',
                    'o_datatype' => 'http://www.w3.org/2001/XMLSchema#decimal',
                    'o_lang' => '',
                ],
            ],
            $sut->getTriples()
        );
    }
}

<?php

/**
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-2 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Benjamin Nowack
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integration\Store\InMemoryStoreSqlite\Query;

use simpleRdf\BlankNode;
use simpleRdf\Literal;
use simpleRdf\NamedNode;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Tests for query method - focus on SELECT queries.
 *
 * Output format is: instances
 */
class SelectQueryOutputInstancesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectUnderTest = InMemoryStoreSqlite::createInstance();
    }

    public function testSelect1()
    {
        // test data
        $this->subjectUnderTest->query('INSERT INTO <http://example.com/> {
            <http://s> <http://p1> "baz" .
            _:foo <http://p1> "baz"^^xsd:string .
            _:foo2 <p2> "baz"@de .
        }');

        $result = $this->subjectUnderTest->query('SELECT * WHERE {?s ?p ?o.}', 'instances');

        $this->assertEquals(
            [
                [
                    's' => new NamedNode('http://s'),
                    'p' => new NamedNode('http://p1'),
                    'o' => new Literal('baz'),
                ],
                [
                    's' => new BlankNode($result[1]['s']->getValue()), // dynamic value
                    'p' => new NamedNode('http://p1'),
                    'o' => new Literal('baz', null, 'http://www.w3.org/2001/XMLSchema#string'),
                ],
                [
                    's' => new BlankNode($result[2]['s']->getValue()), // dynamic value
                    'p' => new NamedNode(NamespaceHelper::BASE_NAMESPACE.'p2'),
                    'o' => new Literal('baz', 'de'),
                ],
            ],
            $result
        );
    }
}

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

namespace Tests\Integration\Store\InMemoryStoreSqlite\SPARQL11;

/**
 * Runs W3C tests from https://www.w3.org/2009/sparql/docs/tests/.
 *
 * Version: 2012-10-23 20:52 (sparql11-test-suite-20121023.tar.gz)
 *
 * Tests are located in the w3c-tests folder.
 *
 * @group linux
 */
class AggregatesTest extends ComplianceTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->w3cTestsFolderPath = __DIR__.'/w3c-tests/aggregates';
        $this->testPref = 'http://www.w3.org/2009/sparql/docs/tests/data-sparql11/aggregates/manifest#';
    }

    /*
     * tests
     */

    public function testAggAvg01()
    {
        $this->loadManifestFileIntoStore($this->w3cTestsFolderPath);

        $testname = 'agg-avg-01';

        // get test data
        $data = $this->getTestData($this->testPref.$testname);

        // load test data into graph
        $this->store->insert($data, $this->dataGraphUri);

        // get query to test
        $testQuery = $this->getTestQuery($this->testPref.$testname);

        // get actual result for given test query
        $actualResult = $this->store->query($testQuery);
        $actualResultAsXml = $this->getXmlVersionOfResult($actualResult);

        // SQLite related
        $this->assertEquals(2.22, (string) $actualResultAsXml->results->result->binding->literal[0]);
    }

    public function testAggEmptyGroup()
    {
        $this->assertTrue($this->runTestFor('agg-empty-group'));
    }

    public function testAggMin01()
    {
        $this->markTestSkipped(
            'Skipped, because of known bug that our Turtle parser can not parse decimals. '
            .'For more information, see https://github.com/semsol/arc2/issues/136'
        );
    }

    public function testAgg01()
    {
        $this->assertTrue($this->runTestFor('agg01'));
    }

    public function testAgg02()
    {
        $this->assertTrue($this->runTestFor('agg02'));
    }

    public function testAgg04()
    {
        $this->assertTrue($this->runTestFor('agg04'));
    }

    public function testAgg05()
    {
        $this->assertTrue($this->runTestFor('agg05'));
    }

    /*
     * agg08 fails
     */

    public function testAgg09()
    {
        $this->assertTrue($this->runTestFor('agg09'));
    }

    public function testAgg10()
    {
        $this->assertTrue($this->runTestFor('agg10'));
    }

    /*
     * agg11, agg12 fails
     */
}

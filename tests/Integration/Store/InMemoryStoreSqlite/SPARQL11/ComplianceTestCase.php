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

namespace Tests\Integration\Store\InMemoryStoreSqlite\SPARQL11;

use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;
use Tests\TestCase;

/**
 * Runs W3C tests from https://www.w3.org/2009/sparql/docs/tests/.
 *
 * Version: 2012-10-23 20:52 (sparql11-test-suite-20121023.tar.gz)
 *
 * Tests are located in the w3c-tests folder.
 */
class ComplianceTestCase extends TestCase
{
    protected InMemoryStoreSqlite $store;

    protected string $dataGraphUri;

    protected string $manifestGraphUri;

    protected string $testPref;

    protected string $w3cTestsFolderPath;

    protected function setUp(): void
    {
        parent::setUp();

        // set graphs
        $this->dataGraphUri = 'http://arc/data/';
        $this->manifestGraphUri = 'http://arc/manifest/';

        /*
         * Setup a store instance to load test information and data.
         */
        $this->store = InMemoryStoreSqlite::createInstance();
    }

    /**
     * Helper function to get expected query result.
     *
     * @return \SimpleXMLElement instance of \SimpleXMLElement representing the result
     */
    protected function getExpectedResult(string $testUri)
    {
        /*
            example:

            :group1 mf:result <group01.srx>
         */
        $res = $this->store->query('
            PREFIX mf: <http://www.w3.org/2001/sw/DataAccess/tests/test-manifest#> .
            SELECT * FROM <'.$this->manifestGraphUri.'> WHERE {
                <'.$testUri.'> mf:result ?resultFile .
            }
        ');

        // if no result was given, expect test is of type NegativeSyntaxTest11,
        // which has no data (group-data-X.ttl) and result (.srx) file.
        if (0 < \count($res['result']['rows'])) {
            return new \SimpleXMLElement(file_get_contents($res['result']['rows'][0]['resultFile']));
        } else {
            return null;
        }
    }

    /**
     * Helper function to get test query for a given test.
     *
     * @param string $testUri
     *
     * @return string query to test
     */
    protected function getTestQuery($testUri)
    {
        /*
            example:

            :group1 mf:action [
                qt:query  <group01.rq>
            ]
         */
        $query = $this->store->query('
            PREFIX mf: <http://www.w3.org/2001/sw/DataAccess/tests/test-manifest#> .
            PREFIX qt: <http://www.w3.org/2001/sw/DataAccess/tests/test-query#> .
            SELECT * FROM <'.$this->manifestGraphUri.'> WHERE {
                <'.$testUri.'> mf:action [ qt:query ?queryFile ] .
            }
        ');

        // if test is of type NegativeSyntaxTest11, mf:action points not to a blank node,
        // but directly to the query file.
        if (0 == \count($query['result']['rows'])) {
            $query = $this->store->query('
                PREFIX mf: <http://www.w3.org/2001/sw/DataAccess/tests/test-manifest#> .
                SELECT * FROM <'.$this->manifestGraphUri.'> WHERE {
                    <'.$testUri.'> mf:action ?queryFile .
                }
            ');
        }

        $query = file_get_contents($query['result']['rows'][0]['queryFile']);

        // add data graph information as FROM clause, because store can't handle default graph
        // queries. for more information see https://github.com/semsol/arc2/issues/72.
        if (false !== strpos($query, 'ASK')
            || false !== strpos($query, 'CONSTRUCT')
            || false !== strpos($query, 'SELECT')) {
            $query = str_replace('WHERE', 'FROM <'.$this->dataGraphUri.'> WHERE', $query);
        }

        return $query;
    }

    /**
     * Helper function to get test type.
     *
     * @param string $testUri
     *
     * @return string Type URI
     */
    protected function getTestType($testUri)
    {
        $type = $this->store->query('
            PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            SELECT * FROM <'.$this->manifestGraphUri.'> WHERE {
                <'.$testUri.'> rdf:type ?type .
            }
        ');

        return $type['result']['rows'][0]['type'];
    }

    /**
     * Transforms query result to a \SimpleXMLElement instance for later comparison.
     *
     * @return \SimpleXMLElement
     */
    protected function getXmlVersionOfResult(array $result)
    {
        $w = new \XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0');

        // sparql (root element)
        $w->startElement('sparql');
        $w->writeAttribute('xmlns', 'http://www.w3.org/2005/sparql-results#');

        // sparql > head
        $w->startElement('head');

        foreach ($result['result']['variables'] as $var) {
            $w->startElement('variable');
            $w->writeAttribute('name', $var);
            $w->endElement();
        }

        // end sparql > head
        $w->endElement();

        // sparql > results
        $w->startElement('results');

        foreach ($result['result']['rows'] as $row) {
            /*
                example:

                <result>
                  <binding name="s">
                    <uri>http://example/s1</uri>
                  </binding>
                </result>
             */

            // new result element
            $w->startElement('result');

            foreach ($result['result']['variables'] as $var) {
                if (empty($row[$var])) {
                    continue;
                }

                // sparql > results > result > binding
                $w->startElement('binding');
                $w->writeAttribute('name', $var);

                // if a variable type is set
                if (isset($row[$var.' type'])) {
                    // uri
                    if ('uri' == $row[$var.' type']) {
                        // example: <uri>http://example/s1</uri>
                        $w->startElement('uri');
                        $w->text($row[$var]);
                        $w->endElement();
                    } elseif ('literal' == $row[$var.' type']) {
                        // example: <literal datatype="http://www.w3.org/2001/XMLSchema#integer">9</literal>
                        $w->startElement('literal');

                        // its not part of the result set, but expected later on
                        if (true === ctype_digit($row[$var])) {
                            $w->writeAttribute('datatype', 'http://www.w3.org/2001/XMLSchema#integer');
                        }

                        $w->text($row[$var]);
                        $w->endElement();
                    }
                }

                // end sparql > results > result > binding
                $w->endElement();
            }

            // end result
            $w->endElement();
        }

        // add <result></result> if no data were found
        if (0 == \count($result['result']['rows'])) {
            $w->startElement('result');
            $w->endElement();
        }

        // end sparql > results
        $w->endElement();

        // end sparql
        $w->endElement();

        return new \SimpleXMLElement($w->outputMemory(true));
    }

    /**
     * @param string $query
     */
    protected function makeQueryA1Liner($query)
    {
        return preg_replace('/\s\s+/', ' ', $query);
    }
}

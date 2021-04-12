# sweetrdf - RDF In-Memory Quad Store

![CI](https://github.com/sweetrdf/in-memory-store-sqlite/workflows/Tests/badge.svg)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/?branch=master)

RDF in-memory quad store implementation using PDO and SQLite.

## Installation

To install this library use Composer via:

> composer require sweetrdf/in-memory-store-sqlite

## Usage

Use `InMemoryStoreSqlite::createInstance()` to get a ready-to-use store instance (see example below).
Sending SPARQL queries can be done via `query` method.
Your data is stored inside an in-memory SQLite database file.
**After the script ends all your data inside the store will be gone**.

## Examples

### Example 1 (First steps)

Create a store instance, load a few triples into it and run a query.

```php
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;

// get ready to use store instance
$store = InMemoryStoreSqlite::createInstance();

// send a SPARQL query which creates two triples
$store->query('INSERT INTO <http://example.com/> {
    <http://s> <http://p1> "baz" .
    <http://s> <http://xmlns.com/foaf/0.1/name> "label1" .
}');

// send another SPARQL query asking for all triples
$res = $store->query('SELECT * WHERE {?s ?p ?o.}');
$triples = $res['result']['rows'];
echo \count($triples); // outputs: 2
// $triples contains result set, which consists of arrays and scalar values
```

### Example 2 (Consume SPARQL client result)

Use `sweetrdf/sparql-client` and a PSR-7-compatible library (like `guzzlehttp/guzzle`) to query a SPARQL endpoint.
For now you have to "transform" SPARQL result set to a quad-list manually.
Afterwards add quads to store and query it.
Check test `testSparqlClientCompatibility` in [InMemoryStoreSqliteTest.php](tests/Integration/Store/InMemoryStoreSqliteTest.php) for a working example.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use simpleRdf\Connection;
use simpleRdf\DataFactory;

/*
 * get data from a SPARQL endpoint
 */
$httpClient = new Client();
$dataFactory = new DataFactory();
$connection = new Connection($httpClient, $dataFactory);
$query = 'SELECT * WHERE {?s ?p ?o} limit 5';
// set SPARQL endpoint URL
$url = 'https://arche-sparql.acdh-dev.oeaw.ac.at/sparql?query=';
$query = new Request('GET', $url.rawurlencode($query));
$statement = $connection->query($query);

/*
 * add result to the store
 */
$quads = [];
foreach ($statement as $entry) {
    // $entry is an object; s, p and o are variables from your query
    $quads[] = $dataFactory->quad($entry->s, $entry->p, $entry->o);
}
$store = InMemoryStoreSqlite::createInstance();
$store->addQuads($quads);

// send query and check result
$result = $store->query('SELECT * WHERE {?s ?p ?o.}');
echo count($result['result']['rows']); // outputs 5
```

## SPARQL support

Store supports a lot of SPARQL 1.0/1.1 features.
For more information please read [SPARQL-support.md](doc/SPARQL-support.md).

## Performance

Store uses an in-memory SQLite file configured with:

* `PRAGMA synchronous = OFF`
* `PRAGMA journal_mode = OFF`
* `PRAGMA locking_mode = EXCLUSIVE`
* `PRAGMA page_size = 4096`

Check [PDOSQLiteAdapter.php](src/PDOSQLiteAdapter.php#L45) for more information.

When adding several hundred or more triples at once you may experience increased execution time.
Local tests showed that per second around 1500 triples can be added.

## License

This work is licensed under the terms of the GPL 3 or later.

## Acknowledgement

This work is based on the code of ARC2 from https://github.com/semsol/arc2 (by Benjamin Nowak and contributors).
To see what was extracted check pull request [#1](https://github.com/sweetrdf/in-memory-store-sqlite/pull/1).
ARC2 is dual licensed under the terms of GPL 2 (or later) as well as W3C Software License.

# RDF In-Memory Quad Store

![CI](https://github.com/sweetrdf/in-memory-store-sqlite/workflows/Tests/badge.svg)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/?branch=master)

Proof-of-concept RDF in-memory quad store implementation using PDO and SQLite. Not ready for production use.

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

This work is licensed under the terms of the GPL 2.

## Acknowledgement

This work is based on the code of ARC2 from https://github.com/semsol/arc2 (by Benjamin Nowak and contributors).
To see what was extracted check pull request [#1](https://github.com/sweetrdf/in-memory-store-sqlite/pull/1).
ARC2 is dual licensed under the terms of GPL 2 (or later) as well as W3C Software License.

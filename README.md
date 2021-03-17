# sweetrdf - RDF In-Memory Quad Store (SQLite)

![CI](https://github.com/sweetrdf/in-memory-store-sqlite/workflows/Tests/badge.svg)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/sweetrdf/in-memory-store-sqlite/?branch=master)

RDF in-memory quad store implementation using PDO and SQLite.

## Installation

Use Composer to install this library using:

> composer install sweetrdf/in-memory-store-sqlite

## Usage

Use `InMemoryStoreSqlite::createInstance()` to get a ready-to-use store instance (see example below).
Sending SPARQL queries can be done via `query` method.
Your data is stored inside an in-memory SQLite database file per default.
After the script ends all your data inside the store will be gone.

### Example

```php

use sweetrdf\InMemoryStoreSqlite\Log\LoggerPool;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;

// fast way
$store = InMemoryStoreSqlite::createInstance();
// or a way with more data control
$store = new InMemoryStoreSqlite(new PDOSQLiteAdapter(), new LoggerPool());

// send a SPARQL query which creates two triples
$store->query('INSERT INTO <http://example.com/> {
    <http://s> <http://p1> "baz" .
    <http://s> <http://xmlns.com/foaf/0.1/name> "label1" .
}');

// send another SPARQL query asking for all triples
$res = $store->query('SELECT * WHERE {?s ?p ?o.}');
echo \count($res['result']['rows']); // outputs: 2
```

## SPARQL support

Store supports a lot of SPARQL 1.0/1.1 features.
For more information please read [SPARQL-support.md](doc/SPARQL-support.md).

## Performance

At around 1000+ triples you may experience increased execution time.

## License

This work is licensed under the terms of the GPL 3 or later.

## Acknowledgement

This work is based on the code of ARC2 from https://github.com/semsol/arc2 (by Benjamin Nowak and contributors).
ARC2 is dual licensed under the terms of GPL 2 (or later) as well as W3C Software License.

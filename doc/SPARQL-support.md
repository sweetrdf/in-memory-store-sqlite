# SPARQL support

## Introduction

This store supports many [SPARQL Query Language](http://www.w3.org/TR/rdf-sparql-query/) features ([to a certain extent](http://www.w3.org/2001/sw/DataAccess/tests/implementations)) and also a number of pragmatic extensions such as aggregates (AVG / COUNT / MAX / MIN / SUM) and write mechanisms.
The changes to the SPARQL specification were kept at a minimum, so that the existing grammar parser and store functionality can be re-used.

This page documents the core differences between SPARQL and what is called "SPARQL+" (originally from in [ARC2](https://github.com/semsol/ARC2)).

## SELECT

### Aggregates
```sql
SELECT COUNT(?contact) AS ?contacts WHERE {
  <#me> foaf:knows ?contact .
}
ORDER BY DESC(?contacts)
```
Note that the alias (... AS ...) has to be specified.


If you have more than a single result variable, you also have to provide GROUP BY information:
```sql
SELECT ?who COUNT(?contact) AS ?contacts WHERE {
  ?who foaf:knows ?contact .
}
GROUP BY ?who
```

ARC2 currently has a bug in the `SUM` ([link](https://github.com/sweetrdf/in-memory-store-sqlite/issues/3)) and `AVG` ([link](https://github.com/sweetrdf/in-memory-store-sqlite/issues/4) function.

#### Supported aggregate functions

|         | AVG                                                                           | COUNT | MIN | MAX | SUM                                                                         |
|---------|-------------------------------------------------------------------------------|-------|-----|-----|-----------------------------------------------------------------------------|
| Support | x (but [bugged](https://github.com/sweetrdf/in-memory-store-sqlite/issues/4)) | x     | x   | x   | (but [bugged](https://github.com/sweetrdf/in-memory-store-sqlite/issues/4)) |


### Supported relational terms

|         | = | != | < | > |
|---------|---|----|---|---|
| Support | x | x  | x | x |

### Supported FILTER functions

|         | bound | datatype | isBlank | isIri | isLiteral | isUri | lang | langMatches | regex | str |
|---------|-------|----------|---------|-------|-----------|-------|------|-------------|-------|-----|
| Support | x     | x        | x       | x     | x         | x     | x    | x           | x     | x   |

## INSERT INTO
```sql
INSERT INTO <http://example.com/> {
 <#foo> <bar> "baz" .
}
```
In this INSERT form the triples have to be fully specified, variables are not allowed.

It is possible to dynamically generate the triples that should be inserted:
```sql
INSERT INTO <http://example.com/inferred> {
  ?s foaf:knows ?o .
}
WHERE {
  ?s xfn:contact ?o .
}
```

## DELETE

```sql
DELETE {
 <#foo> <bar> "baz" .
 <#foo2> <bar2> ?any .
}
```
Each specified triple will be deleted from the RDF store. It is possible to specify variables as wildcards, but they can't be used to build connected patterns. Each triple is handled as a stand-alone pattern.


FROM can be used to restrict the delete operations to selected graphs. It's also possible to not specify any triples. The whole graph will then be deleted.
```sql
DELETE FROM <http://example.com/archive>
```

DELETE can be combined with a WHERE query, like:

```sql
DELETE FROM <http://example.com/inferred> {
  ?s rel:wouldLikeToKnow ?o .
}
WHERE {
  ?s kiss:kissed ?o .
}
```

Instead of deleting triples only in one graph, you can in all graphs by using:

```sql
DELETE {
  ?s rel:wouldLikeToKnow ?o .
}
WHERE {
  ?s kiss:kissed ?o .
}
```

## SPARQL Grammar Changes and Additions
```sql
Query ::= Prologue ( SelectQuery | DescribeQuery | AskQuery | InsertQuery | DeleteQuery )

SelectQuery ::= 'SELECT' ( 'DISTINCT' | 'REDUCED' )? ( Aggregate+ | Var+ | '*' ) DatasetClause* WhereClause SolutionModifier

Aggregate ::= ( 'AVG' | 'COUNT' | 'MAX' | 'MIN' | 'SUM' ) '(' Var | '*' ')' 'AS' Var

InsertQuery ::= 'INSERT' 'INTO' IRIref DatasetClause* WhereClause? SolutionModifier

DeleteQuery ::= 'DELETE' ( 'FROM' IRIref )* DatasetClause* WhereClause? SolutionModifier

SolutionModifier ::= GroupClause? OrderClause? LimitOffsetClauses?

GroupClause ::= 'GROUP' 'BY' Var ( ',' Var )*
```

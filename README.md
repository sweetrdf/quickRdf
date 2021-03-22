# quickRdf

[![Latest Stable Version](https://poser.pugx.org/sweetrdf/quick-rdf/v/stable)](https://packagist.org/packages/sweetrdf/quick-rdf)
![Build status](https://github.com/sweetrdf/quickRdf/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/sweetrdf/quickRdf/badge.svg?branch=master)](https://coveralls.io/github/sweetrdf/quickRdf?branch=master)
[![License](https://poser.pugx.org/sweetrdf/quick-rdf/license)](https://packagist.org/packages/sweetrdf/quick-rdf)

An RDF library for PHP providing implemention of https://github.com/sweetrdf/rdfInterface terms and dataset.

Implemented to be fast and memory efficient.

This is achieved by using a global terms hash table with only a single copy of equal rdfInterface terms being stored in memory at a given time.
It allows to save quite some memory in dense graphs and provides a lightning fast terms comparison (equal if and only if it's the same object).
It also provides a very efficient implementation of Dataset set operations using PHP's SplObjectStorage.

The Dataset implementation maintains indexes allowing quick quads search 
(indexing can be turned off if needed as it significantly slows down a dataset creation).

## Installation

* Obtain the [Composer](https://getcomposer.org)
* Run `composer require sweetrdf/quick-rdf`
* Run `composer require sweetrdf/quick-rdf-io` to install parsers and serializers.

## Automatically generated documentation

https://sweetrdf.github.io/quickRdf/

It's very incomplite but better than nothing.\
[RdfInterface](https://github.com/sweetrdf/rdfInterface/) documentation is included which explains the most important design decisions.

## Usage

```php
include 'vendor/autoload.php';

use quickRdf\DataFactory as DF;

$graph = new quickRdf\Dataset();
$parser = new quickRdfIo\TriGParser();
$stream = fopen('pathToTurtleFile', 'r');
$graph->add($parser->parseStream($stream));
fclose($stream);

// count edges in the graph
echo count($graph);

// go trough all edges in the graph
foreach ($graph as $i) {
  echo "$i\n";
}

// find all graph edges with a given subject
echo $graph->copy(DF::quadTemplate(DF::namedNode('http://mySubject')));

// find all graph edges with a given predicate
echo $graph->copy(DF::quadTemplate(null, DF::namedNode('http://myPredicate')));

// find all graph edges with a given object
echo $graph->copy(DF::quadTemplate(null, null, DF::literal('value', 'en')));

// replace an edge in the graph
$edge = DF::quad(DF::namedNode('http://edgeSubject'), DF::namedNode('http://edgePredicate'), DF::namedNode('http://edgeObject'));
$graph[$edge] = $edge->withObject(DF::namedNode('http://anotherObject'));

// find intersection with other graph
$graph->copy($otherGraph); // immutable
$graph->delete($otherGraph); // in-place

// compute union with other graph
$graph->union($otherGraph); // immutable
$graph->add($otherGraph); // in-place

// compute set difference with other graph
$graph->copyExcept($otherGraph); // immutable
$graph->delete($otherGraph); // in-place

$serializer = new quickRdfIo\TurtleSerializer();
$stream = fopen('pathToOutputTurtleFile', 'w');
$serializer->serializeStream($stream, $graph);
fclose($stream);
```

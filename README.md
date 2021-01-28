# quickRdf

[![Latest Stable Version](https://poser.pugx.org/zozlak/quick-rdf/v/stable)](https://packagist.org/packages/zozlak/quick-rdf)
![Build status](https://github.com/zozlak/quickRdf/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/zozlak/quickRdf/badge.svg?branch=master)](https://coveralls.io/github/zozlak/quickRdf?branch=master)
[![License](https://poser.pugx.org/zozlak/quicki-rdf/license)](https://packagist.org/packages/zozlak/quick-rdf)

An RDF library for PHP implementing the https://github.com/zozlak/rdfInterface interface.

## Installation

* Obtain the [Composer](https://getcomposer.org)
* Run `composer require zozlak/quick-rdf`
* Run `composer require zozlak/quick-rdf-io` to install parsers and serializers.

## Usage

```
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

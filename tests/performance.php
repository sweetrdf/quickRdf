#!/usr/bin/php
<?php
# run with XDEBUG_CONFIG=mode=off ./aaa.php 

require_once 'vendor/autoload.php';

use EasyRdf\Graph;
use quickRdf\TriGParser;
use quickRdf\Dataset;
use quickRdf\DataFactory as DF;

$t = microtime(true);
$g = new Graph();
$g->parseFile(__DIR__ . '/tests/quickRdf/puzzle4d_100k.ntriples', 'application/n-triples');
$t = microtime(true) - $t;
echo "\nParsing time\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . $g->countTriples() . " quads\n";

$t = microtime(true);
$d = $g->resource('https://id.acdh.oeaw.ac.at/td-archiv/MobileObjects_Funde_E19/Inventarbuecher_Datenbanken_Tabellen_Fundzettel/Inventarbuecher/Keramik_07217-07349A/TD_Inv_4DPuzzle1936__TD_7230.tif');
$t = microtime(true) - $t;
echo "\nEasyRdf subject search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    ? quads\n";

$t = microtime(true);
$d = $g->resourcesMatching('https://vocabs.acdh.oeaw.ac.at/schema#hasCurator');
$t = microtime(true) - $t;
echo "\nEasyRdf predicate search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";

$t = microtime(true);
$d = [];
foreach($g->reversePropertyUris('https://id.acdh.oeaw.ac.at/sstuhec') as $prop) {
    $d = array_merge($d, $g->resourcesMatching($prop, $g->resource('https://id.acdh.oeaw.ac.at/sstuhec')));
}
$t = microtime(true) - $t;
echo "\nEasyRdf object search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";

unset($g);

$t = microtime(true);
$p = new TriGParser();
$g = new Dataset();
$g->add($p->parseStream(fopen(__DIR__ . '/tests/quickRdf/puzzle4d_100k.ntriples', 'r')));
$t = microtime(true) - $t;
echo "\nParsing time\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($g) . " quads\n";

$t = microtime(true);
$d = $g->copy(DF::quadTemplate(subject: DF::namedNode('https://id.acdh.oeaw.ac.at/td-archiv/MobileObjects_Funde_E19/Inventarbuecher_Datenbanken_Tabellen_Fundzettel/Inventarbuecher/Keramik_07217-07349A/TD_Inv_4DPuzzle1936__TD_7230.tif')));
$t = microtime(true) - $t;
echo "\nIndexed subject search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";
echo $d;

$t = microtime(true);
$d = $g->copy(DF::quadTemplate(predicate: DF::namedNode('https://vocabs.acdh.oeaw.ac.at/schema#hasCurator')));
$t = microtime(true) - $t;
echo "\nIndexed predicate search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";

$t = microtime(true);
$d = $g->copy(DF::quadTemplate(object: DF::namedNode('https://id.acdh.oeaw.ac.at/sstuhec')));
$t = microtime(true) - $t;
echo "\nIndexed object search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";

$t  = microtime(true);
$gg = new Dataset(false);
$gg->add($g);
$t  = microtime(true) - $t;
echo "\nCopying to unindexed graph\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($gg) . " quads\n";

$t = microtime(true);
$d = $gg->copy(DF::quadTemplate(subject: DF::namedNode('https://id.acdh.oeaw.ac.at/td-archiv/MobileObjects_Funde_E19/Inventarbuecher_Datenbanken_Tabellen_Fundzettel/Inventarbuecher/Keramik_07217-07349A/TD_Inv_4DPuzzle1936__TD_7230.tif')));
$t = microtime(true) - $t;
echo "\nUnindexed subject search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";

$t = microtime(true);
$d = $gg->copy(DF::quadTemplate(predicate: DF::namedNode('https://vocabs.acdh.oeaw.ac.at/schema#hasCurator')));
$t = microtime(true) - $t;
echo "\nUnidexed predicate search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";

$t = microtime(true);
$d = $gg->copy(DF::quadTemplate(object: DF::namedNode('https://id.acdh.oeaw.ac.at/sstuhec')));
$t = microtime(true) - $t;
echo "\nUnidexed object search\n$t s    " . (int) (memory_get_peak_usage(true) / 1024 / 1024) . " MB    " . (int) (memory_get_usage(true) / 1024 / 1024) . " MB    " . count($d) . " quads\n";

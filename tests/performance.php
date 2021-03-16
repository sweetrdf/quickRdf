#!/usr/bin/php
<?php
# run with XDEBUG_CONFIG=mode=off ./performance.php 

require_once __DIR__ . '/../vendor/autoload.php';

use EasyRdf\Graph;
use quickRdfIo\NQuadsParser;
use quickRdf\Dataset;
use quickRdf\DataFactory as DF;

$testFile = __DIR__ . '/puzzle4d_100k.ntriples';
$testSbj  = 'https://id.acdh.oeaw.ac.at/td-archiv/MobileObjects_Funde_E19/Inventarbuecher_Datenbanken_Tabellen_Fundzettel/Inventarbuecher/Keramik_07217-07349A/TD_Inv_4DPuzzle1936__TD_7230.tif';
$testPred = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCurator';
$testObj  = 'https://id.acdh.oeaw.ac.at/sstuhec';

function printlog(int $n, string $solution, string $test, float $time,
                  ?int $tripCount): void {
    printf("%d\t%.6f\t%d\t%d\t%d\t%s\t%s\n", $n, $time, memory_get_peak_usage(true) / 1024 / 1024, memory_get_usage(true) / 1024 / 1024, $tripCount, $solution, $test);
}
$testCount = (int) ($argv[2] ?? 1);
$test      = $argv[1] ?? '';
if ($test == 'easyrdf') {
    for ($i = 0; $i < $testCount; $i++) {
        $t = microtime(true);
        $g = new Graph();
        $g->parseFile($testFile, 'application/n-triples');
        $t = microtime(true) - $t;
        printlog($i, $argv[1], "parsing", $t, $g->countTriples());

        $t = microtime(true);
        $d = $g->resource($testSbj);
        $t = microtime(true) - $t;
        printlog($i, $argv[1], "subject search", $t, null);

        $t = microtime(true);
        $d = $g->resourcesMatching($testPred);
        $t = microtime(true) - $t;
        printlog($i, $argv[1], "predicate search", $t, count($d));

        $t = microtime(true);
        $d = [];
        foreach ($g->reversePropertyUris($testObj) as $prop) {
            $d = array_merge($d, $g->resourcesMatching($prop, $g->resource($testObj)));
        }
        $t = microtime(true) - $t;
        printlog($i, $argv[1], "object search", $t, count($d));

        unset($g);
    }
} else if (in_array($test, ['idxsafe', 'idxunsafe', 'safe', 'unsafe'])) {
    DF::$enforceConstructor = in_array($argv[1], ['idxsafe', 'safe']);
    for ($i = 0; $i < $testCount; $i++) {
        $t = microtime(true);
        $p = new NQuadsParser(new DF(), false, true);
        $g = new Dataset(in_array($argv[1], ['idxsafe', 'idxunsafe']));
        $f = fopen($testFile, 'r');
        if ($f !== false) {
            $g->add($p->parseStream($f));
            fclose($f);
            $t = microtime(true) - $t;
            printlog($i, $argv[1], "parsing", $t, count($g));
        }

        $t = microtime(true);
        $d = $g->copy(DF::quadTemplate(DF::namedNode($testSbj)));
        $t = microtime(true) - $t;
        printlog($i, $argv[1], "subject search", $t, count($d));

        $t = microtime(true);
        $d = $g->copy(DF::quadTemplate(null, DF::namedNode($testPred)));
        $t = microtime(true) - $t;
        printlog($i, $argv[1], "predicate search", $t, count($d));

        $t = microtime(true);
        $d = $g->copy(DF::quadTemplate(null, null, DF::namedNode($testObj)));
        $t = microtime(true) - $t;
        printlog($i, $argv[1], "object search", $t, count($d));
    }
} else {
    exit("Usage: $argv[0] easyrdf/idxsafe/idxunsafe/safe/unsafe [count=1]\n");
}

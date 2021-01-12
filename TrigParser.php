<?php

/*
 * The MIT License
 *
 * Copyright 2021 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace dumbrdf;

use pietercolpaert\hardf\Util;
use dumbrdf\DataFactory as DF;

/**
 * Description of Parser
 *
 * @author zozlak
 */
class TrigParser implements \rdfInterface\Parser, \rdfInterface\QuadIterator {

    const CHUNK_SIZE = 8192;

    /**
     *
     * @var \pietercolpaert\hardf\TriGParser
     */
    private $parser;
    private $input;

    /**
     *
     * @var \rdfInterface\Quad[]
     */
    private $triplesBuffer;
    private $n;
    private $tmpStream;

    public function __construct() {
        $this->parser = new \pietercolpaert\hardf\TriGParser();
    }

    public function __destruct() {
        $this->closeTmpStream();
    }

    public function parse(string $input): \rdfInterface\QuadIterator {
        $this->closeTmpStream();
        $this->tmpStream = fopen('php://memory', 'r+');
        fwrite($this->tmpStream, $input);
        rewind($this->tmpStream);
        return $this->parseStream($this->tmpStream);
    }

    public function parseStream($input): \rdfInterface\QuadIterator {
        if (!is_resource($input)) {
            throw new RdfException("Input has to be a resource");
        }

        $this->input         = $input;
        $this->n             = -1;
        $this->triplesBuffer = [];
        return $this;
    }

    public function current(): \rdfInterface\Quad {
        return current($this->triplesBuffer);
    }

    public function key(): \scalar {
        return $this->n;
    }

    public function next(): void {
        $el = next($this->triplesBuffer);
        if ($el === false) {
            $this->triplesBuffer = [];
            $this->parser->setTripleCallback(function(?\Exception $e,
                                                      ?array $quad): void {
                if ($e) {
                    throw $e;
                }
                if ($quad) {
                    $sbj  = Util::isBlank($quad['subject']) ? DF::BlankNode($quad['subject']) : DF::NamedNode($quad['subject']);
                    $prop = DF::NamedNode($quad['predicate']);
                    if (substr($quad['object'], 0, 1) !== '"') {
                        $obj = Util::isBlank($quad['object']) ? DF::BlankNode($quad['object']) : DF::NamedNode($quad['object']);
                    } else {
                        $value    = substr($quad['object'], 1, strrpos($quad['object'], '"') - 1); // as Util::getLiteralValue() doesn't work for multiline values
                        $lang     = Util::getLiteralLanguage($quad['object']);
                        $datatype = empty($lang) ? Util::getLiteralType($quad['object']) : '';
                        $obj      = DF::Literal($value, $lang, $datatype);
                    }
                    $graph                 = !empty($quad['graph']) ? DF::namedNode($quad['graph']) : DF::defaultGraph();
                    $this->triplesBuffer[] = new Quad($sbj, $prop, $obj);
                }
            });
            while (!feof($this->input) && count($this->triplesBuffer) === 0) {
                $this->parser->parseChunk(fgets($this->input, self::CHUNK_SIZE));
            }
            $this->parser->parseChunk("\n");
        }
        $this->n++;
    }

    public function rewind(): void {
        $ret = rewind($this->input);
        if ($ret !== true) {
            throw new RdfException("Can't seek in the input stream");
        }
        $this->next();
    }

    public function valid(): bool {
        return current($this->triplesBuffer) !== false;
    }

    private function closeTmpStream(): void {
        if (is_resource($this->tmpStream)) {
            fclose($this->tmpStream);
            $this->tmpStream = null;
        }
    }

}

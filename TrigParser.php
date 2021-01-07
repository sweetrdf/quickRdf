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

    public function __construct() {
        $this->parser = new \pietercolpaert\hardf\TriGParser();
    }

    public function parse(string $input): \rdfInterface\QuadIterator {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $input);
        rewind($stream);
        return $this->parseStream($stream);
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
        echo "current $this->n " . key($this->triplesBuffer) . "\n";
        return current($this->triplesBuffer);
    }

    public function key(): \scalar {
        return $this->n;
    }

    public function next(): void {
        $el = next($this->triplesBuffer);
        if ($el !== false) {
            echo "next simple\n";
        } else {
            echo "next chunk\n";
            $this->triplesBuffer = [];
            $this->parser->setTripleCallback(function(?\Exception $e,
                                                      ?array $triple): void {
                if ($e) {
                    throw $e;
                }
                if ($triple) {
                    echo "  parsed " . $triple['object'] . "\n";
                    $sbj  = Util::isBlank($triple['subject']) ? new BlankNode($triple['subject']) : new NamedNode($triple['subject']);
                    $prop = new NamedNode($triple['predicate']);
                    if (substr($triple['object'], 0, 1) !== '"') {
                        $obj = Util::isBlank($triple['object']) ? new BlankNode($triple['object']) : new NamedNode($triple['object']);
                    } else {
                        $value    = substr($triple['object'], 1, strrpos($triple['object'], '"') - 1); // as Util::getLiteralValue() doesn't work for multiline values
                        $lang     = Util::getLiteralLanguage($triple['object']);
                        $datatype = empty($lang) ? Util::getLiteralType($triple['object']) : '';
                        $obj      = new Literal($value, $lang, $datatype);
                    }
                    $this->triplesBuffer[] = new Quad($sbj, $prop, $obj);
                }
            });
            while (!feof($this->input) && count($this->triplesBuffer) === 0) {
                $this->parser->parseChunk(fgets($this->input, self::CHUNK_SIZE));
            }
            $this->parser->parseChunk("\n");
            echo "  feof: " . (int) feof($this->input) . " bufferCount:" . count($this->triplesBuffer) . "\n";
        }
        $this->n++;
    }

    public function rewind(): void {
        $ret = rewind($this->input);
        echo "rewind $ret\n";
        if ($ret !== true) {
            throw new RdfException("Can't seek in the input stream");
        }
        $this->next();
    }

    public function valid(): bool {
        return current($this->triplesBuffer) !== false;
    }

}

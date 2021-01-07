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

use OutOfBoundsException;
use rdfInterface\NamedNode as iNamedNode;
use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\Literal as iLiteral;
use rdfInterface\Quad as iQuad;
use rdfInterface\Term as iTerm;
use rdfInterface\QuadIterator as iQuadIterator;
use rdfInterface\Dataset as iDataset;
use rdfHelpers\GenericQuadIterator;

/**
 * Description of Graph
 *
 * @author zozlak
 */
class Dataset implements iDataset {

    private $quads = [];

    public function __construct() {
        
    }

    public function __toString(): string {
        $ret = '';
        foreach ($this->quads as $i) {
            $ret .= $i . "\n";
        }
        return $ret;
    }

    public function equals(iDataset $other): bool {
        $n1 = $n2 = 0;
        foreach ($other as $i) {
            $n2 += !($i instanceof iBlankNode);
        }
        foreach ($this->quads as $i) {
            if (!$i instanceof iBlankNode) {
                continue;
            }
            $n1++;
            $nomatch = true;
            foreach ($other as $j) {
                if ($i->equals($j)) {
                    $nomatch = false;
                    break;
                }
            }
            if ($nomatch) {
                return false;
            }
        }
        return $n1 === $n2;
    }

    public function add(iQuadIterator $quads): void {
        foreach ($quads as $i) {
            $this->quads[] = $i;
        }
    }

    public function copy(iQuad|callable|null $filter = null): iDataset {
        $dataset = new Dataset();
        $dataset->add($this);
        return $dataset;
    }

    public function delete(iQuad|iQuadIterator|callable $filter): iDataset {
        if ($filter instanceof iQuad) {
            $filter = new GenericQuadIterator($filter);
        }
        if ($filter instanceof iQuadIterator) {
            $filter = function(iQuad $q, iDataset $d) use ($filter): bool {
                foreach ($filter as $i) {
                    if ($i->equals($q)) {
                        return true;
                    }
                }
                return false;
            };
        }

        $removed = new Dataset();
        $n       = count($this->quads);
        for ($i = 0; $i < $n; $i++) {
            if ($filter($this->quads[$i], $this)) {
                $removed[] = $this->quads[$i];
                unset($this->quads[$i]);
            }
        }
        if (count($removed) > 0) {
            $this->quads = array_values($this->quads);
        }
        return $removed;
    }

    public function filter(iQuad|iQuadIterator|callable $filter): iDataset {
        if (is_callable($filter)) {
            $filter2 = function(iQuad $q, iDataset $d) use ($filter): bool {
                return !$filter($q, $d);
            };
        } else {
            if ($filter instanceof iQuad) {
                $filter = new GenericQuadIterator($filter);
            }
            $filter2 = function(iQuad $q, iDataset $d) use ($filter): bool {
                foreach ($filter as $i) {
                    if ($i->equals($q)) {
                        return false;
                    }
                }
                return true;
            };
        }
        return $this->delete($filter2);
    }

    public function forEach(callable $fn, iQuad|callable|null $filter = null): void {
        $filter ??= function(): bool {
            return true;
        };
        if ($filter instanceof iQuad) {
            $template = $filter;
            $filter   = function(iQuad $x) use($template): bool {
                return $x->equals($template);
            };
        }
        $N = count($this->quads);
        for ($i = 0; $i < $N; $i++) {
            if ($filter($this->quads[$i])) {
                $this->quads[$i] = $fn($this->quads[$i], $this);
            }
        }
    }

    // QuadIterator

    public function current(): iQuad {
        return current($this->quads);
    }

    public function key(): \scalar {
        return key($this->quads);
    }

    public function next(): void {
        next($this->quads);
    }

    public function rewind(): void {
        reset($this->quads);
    }

    public function valid(): bool {
        return key($this->quads) !== null;
    }

    // Countable

    public function count(): int {
        return count($this->quads);
    }

    // ArrayAccess

    /**
     * 
     * @param int|iQuad|callable $offset
     * @return bool
     */
    public function offsetExists($offset): bool {
        try {
            $offset = $this->findOffset($offset);
            return isset($this->quads[$offset]);
        } catch (OutOfBoundsException) {
            return false;
        }
    }

    /**
     * 
     * @param int|iQuadTemplate|callable $offset
     * @return iQuad|iQuadIterator
     */
    public function offsetGet($offset): iQuad|iQuadIterator {
        $offset = $this->findOffset($offset);
        if (!isset($this->quads[$offset])) {
            throw new OutOfBoundsException();
        }
        return $this->quads[$offset];
    }

    /**
     * 
     * @param int|iQuad $offset
     * @param iQuad $value
     * @return int
     */
    public function offsetSet($offset, $value): void {
        $offset               = $this->findOffset($offset);
        $this->quads[$offset] = $value;
    }

    /**
     * 
     * @param int|iQuad $offset
     * @return void
     */
    public function offsetUnset($offset): void {
        $offset      = $this->findOffset($offset);
        unset($this->quads);
        $this->quads = array_values($this->quads);
    }

    private function findOffset(int|iQuad|callable|null $offset): int {
        $n = count($this->quads);
        if ($offset === null || is_scalar($offset) && $offset === $n) {
            return $n;
        } elseif (is_scalar($offset) && $offset < $n) {
            return $offset;
        } elseif (is_callable($offset)) {
            foreach ($this->quads as $n => $i) {
                if ($offset($i, $this)) {
                    return $n;
                }
            }
        } else {
            foreach ($this->quads as $n => $i) {
                if ($i->equals($offset)) {
                    return $n;
                }
            }
        }
        throw new OutOfBoundsException();
    }

}

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

use Generator;
use Iterator;
use OutOfBoundsException;
use SplObjectStorage;
use rdfInterface\Term as iTerm;
use rdfInterface\NamedNode as iNamedNode;
use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\Literal as iLiteral;
use rdfInterface\DefaultGraph as iDefaultGraph;
use rdfInterface\Quad as iQuad;
use rdfInterface\QuadTemplate as iQuadTemplate;
use rdfInterface\QuadIterator as iQuadIterator;
use rdfInterface\Dataset as iDataset;
use rdfInterface\DatasetMapReduce as iDatasetMapReduce;
use rdfInterface\DatasetCompare as iDatasetCompare;
use rdfHelpers\GenericQuadIterator;

/**
 * Description of Graph
 *
 * @author zozlak
 */
class Dataset implements iDataset, iDatasetMapReduce, iDatasetCompare {

    /**
     *
     * @var SplObjectStorage<iQuad, mixed>
     */
    private SplObjectStorage $quads;

    /**
     *
     * @var SplObjectStorage<iTerm, mixed>
     */
    private SplObjectStorage $subjectIdx;

    /**
     *
     * @var SplObjectStorage<iTerm, mixed>
     */
    private SplObjectStorage $predicateIdx;

    /**
     *
     * @var SplObjectStorage<iTerm, mixed>
     */
    private SplObjectStorage $objectIdx;

    /**
     *
     * @var SplObjectStorage<iTerm, mixed>
     */
    private SplObjectStorage $graphIdx;
    private bool $indexed;

    public function __construct(bool $indexed = true) {
        $this->quads   = new SplObjectStorage();
        $this->indexed = $indexed;
        if ($this->indexed) {
            $this->subjectIdx   = new SplObjectStorage();
            $this->predicateIdx = new SplObjectStorage();
            $this->objectIdx    = new SplObjectStorage();
            $this->graphIdx     = new SplObjectStorage();
        }
    }

    public function __toString(): string {
        $ret = '';
        foreach ($this->quads as $i) {
            $ret .= $i . "\n";
        }
        return $ret;
    }

    public function equals(iDataset $other): bool {
        $n = 0;
        // $other contained in $this
        foreach ($other as $i) {
            if (!($i->getSubject() instanceof iBlankNode)) {
                if (!isset($this->quads[$i])) {
                    return false;
                }
                $n++;
            }
        }
        // $this contained in $other
        foreach ($this as $i) {
            if (!($i->getSubject() instanceof iBlankNode)) {
                $n--;
            }
        }
        return $n === 0;
    }

    public function add(iQuad | iQuadIterator $quads): void {
        if ($quads instanceof iQuad) {
            $quads = [$quads];
        }
        foreach ($quads as $i) {
            $this->quads->attach($i);
            $this->index($i);
        }
    }

    public function copy(
        iQuad | iQuadTemplate | iQuadIterator | callable | null $filter = null
    ): iDataset {
        $dataset = new Dataset();
        try {
            $dataset->add(new GenericQuadIterator($this->findMatchingQuads($filter)));
        } catch (OutOfBoundsException) {
            
        }
        return $dataset;
    }

    public function copyExcept(
        iQuad | iQuadTemplate | iQuadIterator | callable | null $filter = null
    ): iDataset {
        $dataset = new Dataset();
        $dataset->add(new GenericQuadIterator($this->findNotMatchingQuads($filter)));
        return $dataset;
    }

    public function union(iQuad | iQuadIterator $other): iDataset {
        $ret = new Dataset();
        $ret->add($this);
        $ret->add($other);
        return $ret;
    }

    public function xor(iQuad | iQuadIterator $other): iDataset {
        $ret = $this->union($other);
        $ret->delete($this->copy($other));
        return $ret;
    }

    public function delete(iQuad | iQuadTemplate | iQuadIterator | callable $filter): iDataset {
        $deleted = new Dataset();
        try {
            foreach ($this->findMatchingQuads($filter) as $i) {
                $this->quads->detach($i);
                $this->unindex($i);
                $deleted->add($i);
            }
        } catch (OutOfBoundsException) {
            
        }
        return $deleted;
    }

    public function deleteExcept(iQuad | iQuadTemplate | iQuadIterator | callable $filter): iDataset {
        $deleted = new Dataset();
        foreach ($this->findNotMatchingQuads($filter) as $i) {
            $this->quads->detach($i);
            $this->unindex($i);
            $deleted->add($i);
        }
        return $deleted;
    }

    public function forEach(callable $fn): void {
        foreach (clone $this->quads as $i) {
            $this[$i] = $fn($i, $this);
        }
    }

    // QuadIterator

    public function current(): iQuad {
        return $this->quads->current();
    }

    public function key() {
        return $this->quads->key();
    }

    public function next(): void {
        $this->quads->next();
    }

    public function rewind(): void {
        $this->quads->rewind();
    }

    public function valid(): bool {
        return $this->quads->valid();
    }

    // Countable

    public function count(): int {
        return $this->quads->count();
    }
    // ArrayAccess

    /**
     *
     * @param iQuad|iQuadTemplate|callable $offset
     * @return bool
     */
    public function offsetExists($offset): bool {
        return $this->exists($offset);
    }

    private function exists(iQuad | iQuadTemplate | callable $offset): bool {
        try {
            $iter = $this->findMatchingQuads($offset);
            $this->checkIteratorEnd($iter);
            return true;
        } catch (OutOfBoundsException) {
            return false;
        }
    }

    /**
     *
     * @param iQuad|iQuadTemplate|callable $offset
     * @return iQuad
     */
    public function offsetGet($offset): iQuad {
        return $this->get($offset);
    }

    private function get(iQuad | iQuadTemplate | callable $offset): iQuad {
        $iter = $this->findMatchingQuads($offset);
        $ret  = $iter->current();
        $this->checkIteratorEnd($iter);
        return $ret;
    }

    /**
     *
     * @param iQuad|iQuadTemplate|callable|null $offset
     * @param iQuad $value
     * @return void
     */
    public function offsetSet($offset, $value): void {
        if ($offset === null) {
            $this->add($value);
        } else {
            $this->set($offset, $value);
        }
    }

    private function set(iQuad | iQuadTemplate | callable $offset, iQuad $value): void {
        $iter  = $this->findMatchingQuads($offset);
        $match = $iter->current();
        $this->checkIteratorEnd($iter);
        if ($match !== $value) {
            $this->quads->detach($match);
            $this->unindex($match);
            $this->add($value);
        }
    }

    /**
     *
     * @param iQuad|iQuadTemplate|callable $offset
     * @return void
     */
    public function offsetUnset($offset): void {
        $this->unset($offset);
    }

    private function unset(iQuad | iQuadTemplate | callable $offset): void {
        try {
            foreach ($this->findMatchingQuads($offset) as $quad) {
                $this->quads->detach($quad);
                $this->unindex($quad);
            }
        } catch (OutOfBoundsException) {
            
        }
    }

    /**
     *
     * @param iQuad|iQuadTemplate|iQuadIterator|callable|null $offset
     * @return Generator<iQuad>
     * @throws OutOfBoundsException
     */
    private function findMatchingQuads(
        iQuad | iQuadTemplate | iQuadIterator | callable | null $offset
    ): Generator {
        if ($offset === null) {
            yield from $this->quads;
        } elseif ($offset instanceof iQuad) {
            if (!isset($this->quads[$offset])) {
                throw new OutOfBoundsException();
            }
            yield $offset;
        } elseif ($offset instanceof iQuadTemplate && $this->indexed) {
            yield from $this->findByIndices($offset);
        } elseif ($offset instanceof iQuadTemplate && !$this->indexed || is_callable($offset)) {
            if (is_callable($offset)) {
                $fn = $offset;
            } else {
                $fn = function(iQuad $x) use($offset): bool {
                    return $offset->equals($x);
                };
            }
            $n = 0;
            foreach ($this->quads as $i) {
                if ($fn($i, $this)) {
                    $n++;
                    yield $i;
                }
            }
            if ($n === 0) {
                throw new OutOfBoundsException();
            }
        } elseif ($offset instanceof iQuadIterator) {
            $n = 0;
            foreach ($offset as $i) {
                try {
                    foreach ($this->findMatchingQuads($i) as $j) {
                        $n++;
                        yield $j;
                    }
                } catch (OutOfBoundsException) {
                    
                }
            }
            if ($n == 0) {
                throw new OutOfBoundsException();
            }
        }
    }

    /**
     *
     * @param iQuadTemplate $template
     * @return Generator<iQuad>
     */
    private function findByIndices(iQuadTemplate $template, bool $match = true): Generator {
        $terms   = [
            $template->getSubject(), $template->getPredicate(),
            $template->getObject(), $template->getGraphIri(),
        ];
        $indices = [
            $this->subjectIdx, $this->predicateIdx,
            $this->objectIdx, $this->graphIdx,
        ];

        /* @var $ret  \SplObjectStorage */
        $ret = $match ? null : clone $this->quads;
        for ($i = 0; $i < count($terms); $i++) {
            $term = $terms[$i];
            if ($term !== null && !($term instanceof iDefaultGraph)) {
                $idx = $indices[$i][$term] ?? null;
                if ($idx === null && $match) {
                    throw new OutOfBoundsException();
                }
                if ($ret === null) {
                    $ret = clone $idx;
                } elseif ($match) {
                    $ret->removeAllExcept($idx);
                } else {
                    $ret->removeAll($idx);
                }
                if ($ret->count() === 0 && $match) {
                    throw new OutOfBoundsException();
                }
            }
        }
        yield from $ret;
    }

    /**
     *
     * @param iQuad|iQuadTemplate|iQuadIterator|callable|null $offset
     * @return Generator<iQuad>
     */
    private function findNotMatchingQuads(
        iQuad | iQuadTemplate | iQuadIterator | callable | null $offset
    ): Generator {
        if ($offset === null) {
            yield from $this->quads;
        } elseif ($offset instanceof iQuad) {
            $tmp = clone $this->quads;
            $tmp->detach($offset);
            yield from $tmp;
        } elseif ($offset instanceof iQuadTemplate && $this->indexed) {
            yield from $this->findByIndices($offset, false);
        } elseif ($offset instanceof iQuadTemplate && !$this->indexed || is_callable($offset)) {
            if (is_callable($offset)) {
                $fn = $offset;
            } else {
                $fn = function(iQuad $x) use($offset): bool {
                    return $offset->equals($x);
                };
            }
            foreach ($this->quads as $i) {
                if (!$fn($i, $this)) {
                    yield $i;
                }
            }
        } elseif ($offset instanceof iQuadIterator) {
            $tmp = clone $this->quads;
            foreach ($offset as $i) {
                $tmp->detach($i);
            }
            yield from $tmp;
        }
    }

    private function index(iQuad $quad): void {
        if ($this->indexed) {
            $obj = $quad->getSubject();
            if (!isset($this->subjectIdx[$obj])) {
                $this->subjectIdx[$obj] = new SplObjectStorage();
            }
            $this->subjectIdx[$obj]->attach($quad);

            $obj = $quad->getPredicate();
            if (!isset($this->predicateIdx[$obj])) {
                $this->predicateIdx[$obj] = new SplObjectStorage();
            }
            $this->predicateIdx[$obj]->attach($quad);

            $obj = $quad->getObject();
            if (!isset($this->objectIdx[$obj])) {
                $this->objectIdx[$obj] = new SplObjectStorage();
            }
            $this->objectIdx[$obj]->attach($quad);

            $obj = $quad->getGraphIri();
            // makes no sense to index default graph - all quads belong there
            if (!$obj instanceof iDefaultGraph) {
                if (!isset($this->graphIdx[$obj])) {
                    $this->graphIdx[$obj] = new SplObjectStorage();
                }
                $this->graphIdx[$obj]->attach($quad);
            }
        }
    }

    private function unindex(iQuad $quad): void {
        if ($this->indexed) {
            $obj = $quad->getSubject();
            if (isset($this->subjectIdx[$obj])) {
                $this->subjectIdx[$obj]->detach($quad);
            }

            $obj = $quad->getPredicate();
            if (isset($this->predicateIdx[$obj])) {
                $this->predicateIdx[$obj]->detach($quad);
            }

            $obj = $quad->getObject();
            if (isset($this->objectIdx[$obj])) {
                $this->objectIdx[$obj]->detach($quad);
            }

            $obj = $quad->getGraphIri();
            if (isset($this->graphIdx[$obj])) {
                $this->graphIdx[$obj]->detach($quad);
            }
        }
    }

    /**
     * 
     * @param Iterator<iQuad> $i
     * @return void
     * @throws OutOfBoundsException
     */
    private function checkIteratorEnd(Iterator $i): void {
        $i->next();
        if ($i->key() !== null) {
            throw new OutOfBoundsException("More than one quad matched");
        }
    }

    public function map(callable $fn): iDataset {
        $ret = new Dataset();
        foreach ($this as $i) {
            $ret->add($fn($i, $this));
        }
        return $ret;
    }

    public function reduce(callable $fn, $initialValue = null): mixed {
        foreach ($this as $i) {
            $initialValue = $fn($initialValue, $i, $this);
        }
        return $initialValue;
    }

    public function any(iQuad | iQuadTemplate | iQuadIterator | callable $filter): bool {
        try {
            $iter = $this->findMatchingQuads($filter);
            $iter = $iter->current(); // so PHP doesn't optimize previous line out
            return true;
        } catch (OutOfBoundsException) {
            return false;
        }
    }

    public function every(iQuad | iQuadTemplate | callable $filter): bool {
        try {
            $n = 0;
            foreach ($this->findMatchingQuads($filter) as $i) {
                $n++;
            }
            return $n === $this->count();
        } catch (OutOfBoundsException) {
            return false;
        }
    }

    public function none(iQuad | iQuadTemplate | iQuadIterator | callable $filter): bool {
        try {
            $iter = $this->findMatchingQuads($filter);
            $iter = $iter->current(); // so PHP doesn't optimize previous line out
            return false;
        } catch (OutOfBoundsException) {
            return true;
        }
    }
}

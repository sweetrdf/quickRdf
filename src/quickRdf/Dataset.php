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

namespace quickRdf;

use Generator;
use Iterator;
use OutOfBoundsException;
use SplObjectStorage;
use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\DefaultGraph as iDefaultGraph;
use rdfInterface\Quad as iQuad;
use rdfInterface\Term as iTerm;
use rdfInterface\QuadCompare as iQuadCompare;
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
                if (!($i instanceof Quad)) {
                    $i = DataFactory::importQuad($i);
                }
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
            if (!($i instanceof Quad)) {
                $i = DataFactory::importQuad($i);
            }
            $this->quads->attach($i);
            $this->index($i);
        }
    }

    public function copy(iQuadCompare | iQuadIterator | callable | null $filter = null,
                         bool $indexed = false): iDataset {
        $dataset = new Dataset($indexed);
        try {
            $dataset->add(new GenericQuadIterator($this->findMatchingQuads($filter)));
        } catch (OutOfBoundsException) {
            
        }
        return $dataset;
    }

    public function copyExcept(iQuadCompare | iQuadIterator | callable | null $filter = null,
                               bool $indexed = false): iDataset {
        $dataset = new Dataset($indexed);
        $dataset->add(new GenericQuadIterator($this->findNotMatchingQuads($filter)));
        return $dataset;
    }

    public function union(iQuad | iQuadIterator $other, bool $indexed = false): iDataset {
        $ret = new Dataset($indexed);
        $ret->add($this);
        $ret->add($other);
        return $ret;
    }

    public function xor(iQuad | iQuadIterator $other, bool $indexed = false): iDataset {
        $ret = $this->union($other, $indexed);
        $ret->delete($this->copy($other));
        return $ret;
    }

    public function delete(iQuadCompare | iQuadIterator | callable $filter,
                           bool $indexed = false): iDataset {
        $deleted = new Dataset($indexed);
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

    public function deleteExcept(iQuadCompare | iQuadIterator | callable $filter,
                                 bool $indexed = false): iDataset {
        $deleted = new Dataset($indexed);
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
     * @param iQuadCompare|callable $offset
     * @return bool
     */
    public function offsetExists($offset): bool {
        return $this->exists($offset);
    }

    private function exists(iQuadCompare | callable $offset): bool {
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
     * @param iQuadCompare|callable $offset
     * @return iQuad
     */
    public function offsetGet($offset): iQuad {
        return $this->get($offset);
    }

    private function get(iQuadCompare | callable $offset): iQuad {
        $iter = $this->findMatchingQuads($offset);
        $ret  = $iter->current();
        $this->checkIteratorEnd($iter);
        return $ret;
    }

    /**
     *
     * @param iQuadCompare|callable|null $offset
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

    private function set(iQuadCompare | callable $offset, iQuad $value): void {
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
     * @param iQuadCompare|callable $offset
     * @return void
     */
    public function offsetUnset($offset): void {
        $this->unset($offset);
    }

    private function unset(iQuadCompare | callable $offset): void {
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
     * @param iQuadCompare|iQuadIterator|callable|null $offset
     * @return Generator<iQuad>
     * @throws OutOfBoundsException
     */
    private function findMatchingQuads(iQuadCompare | iQuadIterator | callable | null $offset): Generator {
        if ($offset instanceof iQuad && !($offset instanceof Quad)) {
            $offset = DataFactory::importQuad($offset);
        }

        if ($offset === null) {
            yield from $this->quads;
        } elseif ($offset instanceof iQuad) {
            if (!isset($this->quads[$offset])) {
                throw new OutOfBoundsException();
            }
            yield $offset;
        } elseif ($offset instanceof iQuadCompare && $this->indexed) {
            yield from $this->findByIndices($offset);
        } elseif ($offset instanceof iQuadCompare && !$this->indexed || is_callable($offset)) {
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
     * @param iQuadCompare $template
     * @return Generator<iQuad>
     */
    private function findByIndices(iQuadCompare $template, bool $match = true): Generator {
        /* @var $ret  \SplObjectStorage */
        $ret = null;

        // search using indices
        $terms          = [
            $template->getSubject(), $template->getPredicate(),
            $template->getObject(), $template->getGraph(),
        ];
        $indices        = [
            $this->subjectIdx, $this->predicateIdx,
            $this->objectIdx, $this->graphIdx,
        ];
        $indexableTerms = $notNullTerms   = 0;
        foreach ($terms as $i => &$term) {
            $notNull        = $term !== null && !($term instanceof iDefaultGraph);
            $notNullTerms   += $notNull;
            $indexableTerms += $term instanceof iTerm;
            if (!$notNull || !$term instanceof iTerm) {
                unset($terms[$i]);
                unset($indices[$i]);
            } elseif (!($term instanceof SingletonTerm)) {
                $term = DataFactory::importTerm($term);
            }
        }
        unset($term);
        foreach (array_keys($terms) as $i) {
            $idx = $indices[$i][$terms[$i]] ?? null;
            if ($idx === null) {
                $ret = new SplObjectStorage();
                break;
            }
            if ($ret === null) {
                $ret = clone $idx;
            } else {
                $ret->removeAllExcept($idx);
            }
            if ($ret->count() === 0) {
                break;
            }
        }

        // search non-indexable terms
        if ($indexableTerms !== $notNullTerms) {
            if ($ret === null) {
                $ret = clone $this->quads;
            }
            $ret2 = new SplObjectStorage();
            foreach ($ret as $quad) {
                if ($template->equals($quad)) {
                    $ret2->attach($quad);
                }
            }
            $ret = $ret2;
        }
        if (!$match) {
            $ret2 = clone $this->quads;
            $ret2->removeAll($ret);
            $ret  = $ret2;
        } elseif ($ret->count() === 0) {
            throw new OutOfBoundsException();
        }
        yield from $ret;
    }

    /**
     *
     * @param iQuadCompare|iQuadIterator|callable|null $offset
     * @return Generator<iQuad>
     */
    private function findNotMatchingQuads(iQuadCompare | iQuadIterator | callable | null $offset): Generator {
        if ($offset instanceof iQuad && !($offset instanceof Quad)) {
            $offset = DataFactory::importQuad($offset);
        }

        if ($offset === null) {
            yield from $this->quads;
        } elseif ($offset instanceof iQuad) {
            $tmp = clone $this->quads;
            $tmp->detach($offset);
            yield from $tmp;
        } elseif ($offset instanceof iQuadCompare && $this->indexed) {
            yield from $this->findByIndices($offset, false);
        } elseif ($offset instanceof iQuadCompare && !$this->indexed || is_callable($offset)) {
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
                if (!($i instanceof Quad)) {
                    $i = DataFactory::importQuad($i);
                }
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

            $obj = $quad->getGraph();
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

            $obj = $quad->getGraph();
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

    // DatasetMapReduce

    public function map(callable $fn, bool $indexed = false): iDataset {
        $ret = new Dataset($indexed);
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

    // DatasetCompare

    public function any(iQuadCompare | iQuadIterator | callable $filter): bool {
        try {
            $iter = $this->findMatchingQuads($filter);
            $iter = $iter->current(); // so PHP doesn't optimize previous line out
            return true;
        } catch (OutOfBoundsException) {
            return false;
        }
    }

    public function every(iQuadCompare | callable $filter): bool {
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

    public function none(iQuadCompare | iQuadIterator | callable $filter): bool {
        return !$this->any($filter);
    }
}

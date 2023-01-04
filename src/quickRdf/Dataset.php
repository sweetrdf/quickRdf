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
use rdfInterface\BlankNodeInterface as iBlankNode;
use rdfInterface\DefaultGraphInterface as iDefaultGraph;
use rdfInterface\QuadInterface as iQuad;
use rdfInterface\TermInterface as iTerm;
use rdfInterface\QuadCompareInterface as iQuadCompare;
use rdfInterface\QuadIteratorInterface as iQuadIterator;
use rdfInterface\QuadIteratorAggregateInterface as iQuadIteratorAggregate;
use rdfInterface\TermIteratorInterface as iTermIterator;
use rdfInterface\DatasetInterface as iDataset;
use rdfInterface\DatasetMapReduceInterface as iDatasetMapReduce;
use rdfInterface\DatasetCompareInterface as iDatasetCompare;
use rdfInterface\DatasetListQuadPartsInterface as iDatasetListQuadParts;
use rdfHelpers\GenericQuadIterator;
use rdfHelpers\GenericTermIterator;

/**
 * Description of Graph
 *
 * @author zozlak
 */
class Dataset implements iDataset, iDatasetMapReduce, iDatasetCompare, iDatasetListQuadParts {

    private NamedNode $resourceUri;

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

    public function __construct(bool $indexed = true,
                                ?iNamedNode $resourceUri = null) {
        $this->quads   = new SplObjectStorage();
        $this->indexed = $indexed;
        if ($this->indexed) {
            $this->subjectIdx   = new SplObjectStorage();
            $this->predicateIdx = new SplObjectStorage();
            $this->objectIdx    = new SplObjectStorage();
            $this->graphIdx     = new SplObjectStorage();
        }
        if (!empty($resourceUri)) {
            $this->resourceUri = DataFactory::namedNode($resourceUri);
        }
    }

    public function __toString(): string {
        $ret = '';
        foreach ($this->quads as $i) {
            $ret .= $i . "\n";
        }
        return $ret;
    }

    public function getUri(): NamedNode {
        return $this->resourceUri;
    }

    public function equals(iDataset $other): bool {
        $n = 0;
        // $other contained in $this
        foreach ($other as $i) {
            if (!($i->getSubject() instanceof iBlankNode) && !($i->getObject() instanceof iBlankNode)) {
                if (!($i instanceof Quad) && $i !== null) {
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
            if (!($i->getSubject() instanceof iBlankNode) && !($i->getObject() instanceof iBlankNode)) {
                $n--;
            }
        }
        return $n === 0;
    }

    public function add(iQuad | iQuadIterator | iQuadIteratorAggregate $quads): void {
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

    public function copy(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $filter = null,
                         bool $indexed = false): iDataset {
        $dataset = new Dataset($indexed, $this->resourceUri);
        try {
            $dataset->add(new GenericQuadIterator($this->findMatchingQuads($filter)));
        } catch (OutOfBoundsException) {
            
        }
        return $dataset;
    }

    public function copyExcept(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $filter,
                               bool $indexed = false): iDataset {
        $dataset = new Dataset($indexed, $this->resourceUri);
        $dataset->add(new GenericQuadIterator($this->findNotMatchingQuads($filter)));
        return $dataset;
    }

    public function union(iQuad | iQuadIterator | iQuadIteratorAggregate $other,
                          bool $indexed = false): iDataset {
        $ret = new Dataset($indexed, $this->resourceUri);
        $ret->add($this);
        $ret->add($other);
        return $ret;
    }

    public function xor(iQuad | iQuadIterator | iQuadIteratorAggregate $other,
                        bool $indexed = false): iDataset {
        $ret = $this->union($other, $indexed);
        $ret->delete($this->copy($other));
        return $ret;
    }

    public function delete(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter,
                           bool $indexed = false): iDataset {
        $deleted = new Dataset($indexed);
        try {
            $quads = iterator_to_array($this->findMatchingQuads($filter)); // we need a copy as $this->quads will be modified in-place
            foreach ($quads as $i) {
                $this->quads->detach($i);
                $this->unindex($i);
                $deleted->add($i);
            }
        } catch (OutOfBoundsException) {
            
        }
        return $deleted;
    }

    public function deleteExcept(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter,
                                 bool $indexed = false): iDataset {
        $deleted = new Dataset($indexed);
        $quads   = iterator_to_array($this->findNotMatchingQuads($filter)); // we need a copy as $this->quads will be modified in-place
        foreach ($quads as $i) {
            $this->quads->detach($i);
            $this->unindex($i);
            $deleted->add($i);
        }
        return $deleted;
    }

    public function forEach(callable $fn,
                            iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter = null): void {
        try {
            $quads = iterator_to_array($this->findMatchingQuads($filter)); // we need a copy as $this->quads will be modified in-place
            foreach ($quads as $i) {
                $val = $fn($i, $this);
                if ($val !== $i) {
                    $this->quads->detach($i);
                    $this->unindex($i);
                    if ($val !== null) {
                        $this->quads->attach($val);
                        $this->index($val);
                    }
                }
            }
        } catch (OutOfBoundsException $e) {
            
        }
    }

    // QuadIteratorAggregate

    public function getIterator(\rdfInterface\QuadCompareInterface | \rdfInterface\QuadIteratorInterface | \rdfInterface\QuadIteratorAggregateInterface | callable | null $filter = null): \rdfInterface\QuadIteratorInterface {
        return new GenericQuadIterator($this->findMatchingQuads($filter));
    }

    // Countable

    public function count(): int {
        return $this->quads->count();
    }
    // ArrayAccess

    /**
     *
     * @param iQuadCompare|callable|int $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool {
        return $this->exists($offset);
    }

    private function exists(iQuadCompare | callable | int $offset): bool {
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
     * @param iQuadCompare|callable|int $offset
     * @return iQuad
     */
    public function offsetGet(mixed $offset): iQuad {
        return $this->get($offset);
    }

    private function get(iQuadCompare | callable | int $offset): iQuad {
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

    // DatasetMapReduce

    public function map(callable $fn,
                        iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter = null,
                        bool $indexed = false): iDatasetMapReduce {
        $ret = new Dataset($indexed, $this->resourceUri);
        try {
            $quads = $this->findMatchingQuads($filter);
            foreach ($quads as $i) {
                $ret->add($fn($i, $this));
            }
        } catch (OutOfBoundsException $e) {
            
        }
        return $ret;
    }

    public function reduce(callable $fn, $initialValue = null,
                           iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter = null): mixed {
        try {
            $quads = $this->findMatchingQuads($filter);
            foreach ($quads as $i) {
                $initialValue = $fn($initialValue, $i, $this);
            }
        } catch (OutOfBoundsException $e) {
            
        }
        return $initialValue;
    }

    // DatasetCompare

    public function any(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter): bool {
        try {
            $iter = $this->findMatchingQuads($filter);
            $iter = $iter->current(); // so PHP doesn't optimize previous line out
            return true;
        } catch (OutOfBoundsException) {
            return false;
        }
    }

    public function every(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter): bool {
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

    public function none(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable $filter): bool {
        return !$this->any($filter);
    }

    // DatasetListQuadParts

    public function listSubjects(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $filter = null): iTermIterator {
        return $this->listQuadElement($filter, 'getSubject');
    }

    public function listPredicates(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $filter = null): iTermIterator {
        return $this->listQuadElement($filter, 'getPredicate');
    }

    public function listObjects(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $filter = null): iTermIterator {
        return $this->listQuadElement($filter, 'getObject');
    }

    public function listGraphs(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $filter = null): iTermIterator {
        return $this->listQuadElement($filter, 'getGraph');
    }

    private function listQuadElement(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $filter,
                                     string $elementFn): iTermIterator {
        $spotted = new SplObjectStorage();
        try {
            foreach ($this->findMatchingQuads($filter) as $i) {
                $i = $i->$elementFn();
                if (!$spotted->contains($i)) {
                    $spotted->attach($i);
                }
            }
        } catch (OutOfBoundsException $ex) {
            
        }
        return new GenericTermIterator($spotted);
    }
    // Private Part

    /**
     *
     * @param iQuadCompare|iQuadIterator|iQuadIteratorAggregate|callable|int|null $offset
     * @return Generator<iQuad>
     * @throws OutOfBoundsException
     */
    private function findMatchingQuads(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | int | null $offset): Generator {
        if (is_int($offset) && $offset !== 0) {
            throw new OutOfBoundsException("Only integer offset of 0 is allowed");
        }
        if ($offset instanceof iQuad && !($offset instanceof Quad)) {
            $offset = DataFactory::importQuad($offset);
        }

        if ($offset === null) {
            yield from $this->quads;
        } elseif (is_int($offset)) {
            if (count($this->quads) === 0) {
                throw new OutOfBoundsException();
            }
            $this->quads->rewind();
            yield $this->quads->current();
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
                $fn = function (iQuad $x) use ($offset): bool {
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
        } elseif ($offset instanceof iQuadIterator || $offset instanceof iQuadIteratorAggregate) {
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
     * @param iQuadCompare|iQuadIterator|iQuadIteratorAggregate|callable|null $offset
     * @return Generator<iQuad>
     */
    private function findNotMatchingQuads(iQuadCompare | iQuadIterator | iQuadIteratorAggregate | callable | null $offset): Generator {
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
                $fn = function (iQuad $x) use ($offset): bool {
                    return $offset->equals($x);
                };
            }
            foreach ($this->quads as $i) {
                if (!$fn($i, $this)) {
                    yield $i;
                }
            }
        } elseif ($offset instanceof iQuadIterator || $offset instanceof iQuadIteratorAggregate) {
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
}

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
use UnexpectedValueException;
use SplObjectStorage;
use rdfInterface\BlankNodeInterface;
use rdfInterface\DatasetInterface;
use rdfInterface\DefaultGraphInterface;
use rdfInterface\QuadCompareInterface;
use rdfInterface\QuadInterface;
use rdfInterface\QuadIteratorInterface;
use rdfInterface\QuadIteratorAggregateInterface;
use rdfInterface\TermInterface;
use rdfInterface\TermIteratorInterface;
use rdfInterface\MultipleQuadsMatchedException;
use rdfHelpers\GenericQuadIterator;
use rdfHelpers\GenericTermIterator;

/**
 * Description of Graph
 *
 * @author zozlak
 */
class Dataset implements DatasetInterface {

    use \rdfHelpers\DatasetGettersTrait;

    static public function factory(bool $indexed = true): Dataset {
        return new Dataset($indexed);
    }

    /**
     *
     * @var SplObjectStorage<QuadInterface, mixed>
     */
    private SplObjectStorage $quads;

    /**
     *
     * @var SplObjectStorage<TermInterface, mixed>
     */
    private SplObjectStorage $subjectIdx;

    /**
     *
     * @var SplObjectStorage<TermInterface, mixed>
     */
    private SplObjectStorage $predicateIdx;

    /**
     *
     * @var SplObjectStorage<TermInterface, mixed>
     */
    private SplObjectStorage $objectIdx;

    /**
     *
     * @var SplObjectStorage<TermInterface, mixed>
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

    public function equals(DatasetInterface $other): bool {
        $n = 0;
        // $other contained in $this
        foreach ($other as $i) {
            if (!($i->getSubject() instanceof BlankNodeInterface) && !($i->getObject() instanceof BlankNodeInterface)) {
                if ($i instanceof Quad) {
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
            if (!($i->getSubject() instanceof BlankNodeInterface) && !($i->getObject() instanceof BlankNodeInterface)) {
                $n--;
            }
        }
        return $n === 0;
    }

    /**
     * 
     * @param \rdfInterface\QuadInterface|\rdfInterface\QuadIteratorAggregateInterface|\rdfInterface\QuadIteratorInterface|array<\rdfInterface\QuadInterface> $quads
     * @return void
     */
    public function add(QuadInterface | \Traversable | array $quads): void {
        if ($quads instanceof QuadInterface) {
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

    public function copy(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null,
                         bool $indexed = false): Dataset {
        $dataset = new Dataset($indexed);
        $dataset->add(new GenericQuadIterator($this->findMatchingQuads($filter)));
        return $dataset;
    }

    public function copyExcept(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter,
                               bool $indexed = false): Dataset {
        $dataset = new Dataset($indexed);
        $dataset->add(new GenericQuadIterator($this->findNotMatchingQuads($filter)));
        return $dataset;
    }

    public function union(QuadInterface | QuadIteratorInterface | QuadIteratorAggregateInterface $other,
                          bool $indexed = false): Dataset {
        $ret = new Dataset($indexed);
        $ret->add($this);
        $ret->add($other);
        return $ret;
    }

    public function xor(QuadInterface | QuadIteratorInterface | QuadIteratorAggregateInterface $other,
                        bool $indexed = false): Dataset {
        $ret = $this->union($other, $indexed);
        $ret->delete($this->copy($other));
        return $ret;
    }

    public function delete(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null,
                           bool $indexed = false): Dataset {
        $deleted = new Dataset($indexed);
        $quads   = iterator_to_array($this->findMatchingQuads($filter)); // we need a copy as $this->quads will be modified in-place
        foreach ($quads as $i) {
            $this->quads->detach($i);
            $this->unindex($i);
            $deleted->add($i);
        }
        return $deleted;
    }

    public function deleteExcept(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter,
                                 bool $indexed = false): Dataset {
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
                            QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): void {
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
     * @param QuadCompareInterface|callable|int<0, max> $offset
     * @return bool
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetExists(mixed $offset): bool {
        if (!($offset instanceof QuadCompareInterface || is_callable($offset) || $offset === 0)) {
            throw new UnexpectedValueException();
        }
        return $this->exists($offset);
    }

    private function exists(QuadCompareInterface | callable | int $offset): bool {
        $iter = $this->findMatchingQuads($offset);
        return $this->checkIterator($iter, false) !== null;
    }

    /**
     *
     * @param QuadCompareInterface|callable|int<0, max> $offset
     * @return QuadInterface
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetGet(mixed $offset): QuadInterface {
        if (!($offset instanceof QuadCompareInterface || is_callable($offset) || $offset === 0)) {
            throw new UnexpectedValueException();
        }
        return $this->get($offset);
    }

    private function get(QuadCompareInterface | callable | int $offset): QuadInterface {
        $iter = $this->findMatchingQuads($offset);
        return $this->checkIterator($iter, true);
    }

    /**
     *
     * @param QuadCompareInterface|callable|null|mixed $offset
     * @param QuadInterface $value
     * @return void
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetSet($offset, $value): void {
        if (!($offset instanceof QuadCompareInterface || is_callable($offset) || $offset === null)) {
            throw new UnexpectedValueException();
        }
        if ($offset === null) {
            $this->add($value);
        } else {
            $this->set($offset, $value);
        }
    }

    private function set(QuadCompareInterface | callable $offset,
                         QuadInterface $value): void {
        $iter  = $this->findMatchingQuads($offset);
        $match = $this->checkIterator($iter, true);
        if ($match !== $value) {
            $this->quads->detach($match);
            $this->unindex($match);
            $this->add($value);
        }
    }

    /**
     *
     * @param QuadCompareInterface|callable|mixed $offset
     * @return void
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetUnset($offset): void {
        if (!($offset instanceof QuadCompareInterface || is_callable($offset))) {
            throw new UnexpectedValueException();
        }
        $this->unset($offset);
    }

    private function unset(QuadCompareInterface | callable $offset): void {
        $iter  = $this->findMatchingQuads($offset);
        $match = $this->checkIterator($iter, false);
        if ($match !== null) {
            $this->quads->detach($match);
            $this->unindex($match);
        }
    }

    // DatasetMapReduce

    public function map(callable $fn,
                        QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null,
                        bool $indexed = false): Dataset {
        $ret   = new Dataset($indexed);
        $quads = $this->findMatchingQuads($filter);
        foreach ($quads as $i) {
            $ret->add($fn($i, $this));
        }
        return $ret;
    }

    public function reduce(callable $fn, $initialValue = null,
                           QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): mixed {
        $quads = $this->findMatchingQuads($filter);
        foreach ($quads as $i) {
            $initialValue = $fn($initialValue, $i, $this);
        }
        return $initialValue;
    }

    // DatasetCompare

    public function any(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter): bool {
        $iter = $this->findMatchingQuads($filter);
        return $iter->valid();
    }

    public function every(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter): bool {
        return iterator_count($this->findMatchingQuads($filter)) === $this->count();
    }

    public function none(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter): bool {
        return !$this->any($filter);
    }

    // DatasetListQuadParts

    public function listSubjects(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->listQuadElement($filter, 'getSubject');
    }

    public function listPredicates(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->listQuadElement($filter, 'getPredicate');
    }

    public function listObjects(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->listQuadElement($filter, 'getObject');
    }

    public function listGraphs(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->listQuadElement($filter, 'getGraph');
    }

    private function listQuadElement(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter,
                                     string $elementFn): TermIteratorInterface {
        $spotted = new SplObjectStorage();
        foreach ($this->findMatchingQuads($filter) as $i) {
            $i = $i->$elementFn();
            if (!$spotted->contains($i)) {
                $spotted->attach($i);
            }
        }
        return new GenericTermIterator($spotted);
    }
    // Private Part

    /**
     *
     * @param QuadCompareInterface|QuadIteratorInterface|QuadIteratorAggregateInterface|callable|int<0, max>|null $offset
     * @return Generator<QuadInterface>
     * @throws UnexpectedValueException
     */
    private function findMatchingQuads(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | int | null $offset): Generator {
        if (is_int($offset) && $offset !== 0) {
            throw new UnexpectedValueException("Only integer offset of 0 is allowed");
        }
        if ($offset instanceof QuadInterface && !($offset instanceof Quad)) {
            $offset = DataFactory::importQuad($offset);
        }

        if ($offset === null) {
            yield from $this->quads;
        } elseif (is_int($offset) && count($this->quads) > 0) {
            $this->quads->rewind();
            yield $this->quads->current();
        } elseif ($offset instanceof QuadInterface) {
            if (isset($this->quads[$offset])) {
                yield $offset;
            }
        } elseif ($offset instanceof QuadCompareInterface && $this->indexed) {
            yield from $this->findByIndices($offset);
        } elseif ($offset instanceof QuadCompareInterface && !$this->indexed || is_callable($offset)) {
            if (is_callable($offset)) {
                $fn = $offset;
            } else {
                $fn = function (QuadInterface $x) use ($offset): bool {
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
        } elseif ($offset instanceof QuadIteratorInterface || $offset instanceof QuadIteratorAggregateInterface) {
            $n = 0;
            foreach ($offset as $i) {
                try {
                    foreach ($this->findMatchingQuads($i) as $j) {
                        $n++;
                        yield $j;
                    }
                } catch (UnexpectedValueException) {
                    
                }
            }
        }
    }

    /**
     *
     * @param QuadCompareInterface $template
     * @return Generator<QuadInterface>
     */
    private function findByIndices(QuadCompareInterface $template,
                                   bool $match = true): Generator {
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
            $notNull        = $term !== null && !($term instanceof DefaultGraphInterface);
            $notNullTerms   += $notNull;
            $indexableTerms += $term instanceof TermInterface;
            if (!$notNull || !$term instanceof TermInterface) {
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
        }
        yield from $ret;
    }

    /**
     *
     * @param QuadCompareInterface|QuadIteratorInterface|QuadIteratorAggregateInterface|callable|null $offset
     * @return Generator<QuadInterface>
     */
    private function findNotMatchingQuads(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $offset): Generator {
        if ($offset instanceof QuadInterface && !($offset instanceof Quad)) {
            $offset = DataFactory::importQuad($offset);
        }

        if ($offset === null) {
            yield from $this->quads;
        } elseif ($offset instanceof QuadInterface) {
            $tmp = clone $this->quads;
            $tmp->detach($offset);
            yield from $tmp;
        } elseif ($offset instanceof QuadCompareInterface && $this->indexed) {
            yield from $this->findByIndices($offset, false);
        } elseif ($offset instanceof QuadCompareInterface && !$this->indexed || is_callable($offset)) {
            if (is_callable($offset)) {
                $fn = $offset;
            } else {
                $fn = function (QuadInterface $x) use ($offset): bool {
                    return $offset->equals($x);
                };
            }
            foreach ($this->quads as $i) {
                if (!$fn($i, $this)) {
                    yield $i;
                }
            }
        } elseif ($offset instanceof QuadIteratorInterface || $offset instanceof QuadIteratorAggregateInterface) {
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

    private function index(QuadInterface $quad): void {
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
            if (!$obj instanceof DefaultGraphInterface) {
                if (!isset($this->graphIdx[$obj])) {
                    $this->graphIdx[$obj] = new SplObjectStorage();
                }
                $this->graphIdx[$obj]->attach($quad);
            }
        }
    }

    private function unindex(QuadInterface $quad): void {
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
     * @param Iterator<QuadInterface> $i
     * @return QuadInterface|null
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    private function checkIterator(Iterator $i, bool $errorOrInvalid): QuadInterface | null {
        if (!$i->valid()) {
            if ($errorOrInvalid) {
                throw new UnexpectedValueException();
            } else {
                return null;
            }
        }
        $ret = $i->current();
        $i->next();
        if ($i->valid()) {
            throw new MultipleQuadsMatchedException();
        }
        return $ret;
    }
}

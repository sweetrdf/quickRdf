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

use BadMethodCallException;
use Generator;
use Iterator;
use OutOfBoundsException;
use SplObjectStorage;
use rdfInterface\NamedNode as iNamedNode;
use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\Literal as iLiteral;
use rdfInterface\Quad as iQuad;
use rdfInterface\Term as iTerm;
use rdfInterface\QuadTemplate as iQuadTemplate;
use rdfInterface\QuadIterator as iQuadIterator;
use rdfInterface\Dataset as iDataset;
use rdfHelpers\GenericQuadIterator;

/**
 * Description of Graph
 *
 * @author zozlak
 */
class Dataset implements iDataset
{

    /**
     *
     * @var SplObjectStorage<object, iQuad>
     */
    private SplObjectStorage $quads;

    /**
     *
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $subjectIdx;

    /**
     *
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $predicateIdx;

    /**
     *
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $objectIdx;

    /**
     *
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $graphIdx;
    private bool $indexed;

    public function __construct(bool $indexed = true)
    {
        $this->quads   = new SplObjectStorage();
        $this->indexed = $indexed;
        if ($this->indexed) {
            $this->subjectIdx   = new SplObjectStorage();
            $this->predicateIdx = new SplObjectStorage();
            $this->objectIdx    = new SplObjectStorage();
            $this->graphIdx     = new SplObjectStorage();
        }
    }

    public function __toString(): string
    {
        $ret = '';
        foreach ($this->quads as $i) {
            $ret .= $i . "\n";
        }
        return $ret;
    }

    public function equals(iDataset $other): bool
    {
        $n = 0;
        // $other contained in $this
        foreach ($other as $i) {
            if (!($i instanceof iBlankNode)) {
                if (!isset($this->quads[$i])) {
                    return false;
                }
                $n++;
            }
        }
        // $this contained in $other
        foreach ($this->quads as $i) {
            if (!$i instanceof iBlankNode) {
                $n--;
            }
        }
        return $n === 0;
    }

    public function add(iQuad | iQuadIterator $quads): void
    {
        if ($quads instanceof iQuad) {
            $quads = [$quads];
        }
        foreach ($quads as $i) {
            $this->quads->attach($i);
            $this->index($i);
        }
    }

    public function copy(
        iQuad | iQuadIterator | callable | null $filter = null,
        bool $match = true
    ): iDataset
    {
        $dataset = new Dataset();
        if ($filter === null) {
            $dataset->quads->addAll($this->quads);
            foreach ($dataset->quads as $i) {
                $dataset->index($i);
            }
        } else {
            $dataset->add(new GenericQuadIterator($this->findMatchingQuads($filter)));
        }
        return $dataset;
    }

    public function delete(
        iQuad | iQuadIterator | callable $filter, bool $match = true
    ): iDataset
    {
        return new Dataset();
//        if ($filter instanceof iQuad) {
//            $filter = new GenericQuadIterator($filter);
//        }
//        if ($filter instanceof iQuadIterator) {
//            $filter = function(iQuad $q, iDataset $d) use ($filter): bool {
//                foreach ($filter as $i) {
//                    if ($i->equals($q)) {
//                        return true;
//                    }
//                }
//                return false;
//            };
//        }
//
//        $removed = new Dataset();
//        $n       = count($this->quads);
//        for ($i = 0; $i < $n; $i++) {
//            if ($filter($this->quads[$i], $this)) {
//                $removed[] = $this->quads[$i];
//                unset($this->quads[$i]);
//            }
//        }
//        if (count($removed) > 0) {
//            $this->quads = array_values($this->quads);
//        }
//        return $removed;
    }

    public function forEach(callable $fn, iQuad | callable | null $filter = null): void
    {
//        $filter ??= function(): bool {
//            return true;
//        };
//        if ($filter instanceof iQuad) {
//            $template = $filter;
//            $filter   = function(iQuad $x) use($template): bool {
//                return $x->equals($template);
//            };
//        }
//        $N = count($this->quads);
//        for ($i = 0; $i < $N; $i++) {
//            if ($filter($this->quads[$i])) {
//                $this->quads[$i] = $fn($this->quads[$i], $this);
//            }
//        }
    }

    // QuadIterator

    public function current(): iQuad
    {
        return $this->quads->current();
    }

    public function key()
    {
        return $this->quads->key();
    }

    public function next(): void
    {
        $this->quads->next();
    }

    public function rewind(): void
    {
        $this->quads->rewind();
    }

    public function valid(): bool
    {
        return $this->quads->valid();
    }

    // Countable

    public function count(): int
    {
        return $this->quads->count();
    }
    // ArrayAccess

    /**
     *
     * @param int|iQuad|iQuadTemplate|callable $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->exists($offset);
    }

    private function exists(int | iQuad | iQuadTemplate | callable $offset): bool
    {
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
     * @param int|iQuad|iQuadTemplate|callable $offset
     * @return iQuad
     */
    public function offsetGet($offset): iQuad
    {
        return $this->get($offset);
    }

    private function get(int | iQuad | iQuadTemplate | callable $offset): iQuad
    {
        $iter = $this->findMatchingQuads($offset);
        $ret  = $iter->current();
        $this->checkIteratorEnd($iter);
        return $ret;
    }

    /**
     *
     * @param int|iQuad|iQuadTemplate|callable|null $offset
     * @param iQuad $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->add($value);
        } else {
            $this->set($offset, $value);
        }
    }

    private function set(
        int | iQuad | iQuadTemplate | callable $offset,
        iQuad $value
    ): void
    {
        $iter  = $this->findMatchingQuads($offset);
        $match = $iter->current();
        $this->checkIteratorEnd($iter);
        $this->delete($match);
        $this->add($value);
    }

    /**
     *
     * @param int|iQuad|iQuadTemplate|callable $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        $this->unset($offset);
    }

    private function unset(int | iQuad | iQuadTemplate | callable $offset): void
    {
        try {
            foreach ($this->findMatchingQuads($offset) as $quad) {
                $this->quads->detach($quad);
                $this->unindex($quad);
            }
        } catch (OutOfBoundsException) {
            
        }
    }
//    /**
//     * Checks if $idx contains $term. If not, throws an OutOfBoundsException.
//     * If yes, computes intersection of $valid and $idx content for a given
//     * $term.
//     *
//     * If $term is null, returns $valid.
//     *
//     * If $term is not null and $valid is null returns $idx content for a given
//     * $term.
//     *
//     * @param iTerm|null $term
//     * @param SplObjectStorage $idx
//     * @param SplObjectStorage|null $valid
//     * @return SplObjectStorage|null
//     * @throws OutOfBoundsException
//     */
//    private function searchIndex(
//        iTerm | null $term,
//        SplObjectStorage $idx,
//        SplObjectStorage | null $valid
//    ): SplObjectStorage | null {
//        if ($term === null) {
//            return $valid;
//        }
//        $matches = $idx[$term] ?? throw new OutOfBoundsException();
//        if ($valid === null) {
//            return $matches;
//        }
//        $ret = new SplObjectStorage();
//        foreach ($valid as $i) {
//            if ($matches->contains($i)) {
//                $ret->attach($i);
//            }
//        }
//        if ($ret->count() === 0) {
//            throw new OutOfBoundsException();
//        }
//        return $ret;
//    }

    /**
     *
     * @param int|iQuad|iQuadTemplate|iQuadIterator|callable|null $offset
     * @return Generator<iQuad>
     * @throws OutOfBoundsException
     */
    private function findMatchingQuads(
        int | iQuad | iQuadTemplate | iQuadIterator | callable | null $offset
    ): Generator
    {
        if ($offset === null) {
            yield from $this->quads;
        } elseif (is_callable($offset)) {
            foreach ($this->quads as $i) {
                if ($offset($i, $this)) {
                    yield $i;
                }
            }
        } elseif ($offset instanceof iQuadIterator) {
            foreach ($offset as $i) {
                yield from $this->findMatchingQuads($i);
            }
        } elseif ($offset instanceof iQuadTemplate) {
            if ($this->indexed) {
                yield from $this->findByIndices($offset);
            } else {
                foreach ($this->quads as $i) {
                    if ($offset->equals($i)) {
                        yield $i;
                    }
                }
            }
        } elseif ($offset instanceof iQuad) {
            if (!isset($this->quads[$offset])) {
                throw new OutOfBoundsException();
            }
            yield $offset;
        } elseif (is_numeric($offset)) {
            $offset = (int) $offset;
            if ($offset >= $this->quads->count()) {
                throw new OutOfBoundsException();
            }
            $this->quads->rewind();
            while ($offset > 0) {
                $this->quads->next();
                $offset--;
            }
            yield $this->quads->current();
        }
    }

    /**
     *
     * @param iQuadTemplate $template
     * @return Generator<iQuad>
     */
    private function findByIndices(iQuadTemplate $template): Generator
    {
//        $matches = $this->searchIndex($offset->getSubject(), $this->subjectIdx, null);
//        $matches = $this->searchIndex($offset->getPredicate(), $this->predicateIdx, $matches);
//        $matches = $this->searchIndex($offset->getObject(), $this->objectIdx, $matches);
//        $matches = $this->searchIndex($offset->getGraphIri(), $this->graphIdx, $matches);
        yield from $this->quads;
    }

    private function index(iQuad $quad): void
    {
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
            if (!isset($this->graphIdx[$obj])) {
                $this->graphIdx[$obj] = new SplObjectStorage();
            }
            $this->graphIdx[$obj]->attach($quad);
        }
    }

    private function unindex(iQuad $quad): void
    {
        if ($this->indexed) {
            $obj = $quad->getSubject();
            if (isset($this->subjectIdx[$obj])) {
                $this->subjectIdx[$obj]->detach($quad);
            }

            $obj = $quad->getPredicate();
            if (isset($this->predicateIdx[$obj])) {
                $this->predicateIdx[$obj]->dettach($quad);
            }

            $obj = $quad->getObject();
            if (isset($this->objectIdx[$obj])) {
                $this->objectIdx[$obj]->dettach($quad);
            }

            $obj = $quad->getGraphIri();
            if (isset($this->graphIdx[$obj])) {
                $this->graphIdx[$obj]->dettach($quad);
            }
        }
    }

    private function sanitizeFilterFn(mixed $fn, bool $match): mixed
    {
        if (!is_callable($fn) || $match) {
            return $fn;
        } else {
            return function ($x, $y) use ($fn) {
                return !$fn($x, $y);
            };
        }
    }

    /**
     * 
     * @param Iterator<iQuad> $i
     * @return void
     * @throws OutOfBoundsException
     */
    private function checkIteratorEnd(Iterator $i): void
    {
        $i->next();
        if ($i->key() !== null) {
            throw new OutOfBoundsException("More than one quad matched");
        }
    }
}

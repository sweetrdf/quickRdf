<?php

/*
 * The MIT License
 *
 * Copyright 2023 zozlak.
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

use BadMethodCallException;
use InvalidArgumentException;
use Traversable;
use UnexpectedValueException;
use rdfInterface\DatasetNodeInterface;
use rdfInterface\DatasetInterface;
use rdfInterface\TermInterface;
use rdfInterface\TermCompareInterface;
use rdfInterface\TermIteratorInterface;
use rdfInterface\QuadInterface;
use rdfInterface\QuadIteratorInterface;
use rdfInterface\QuadIteratorAggregateInterface;
use rdfInterface\QuadCompareInterface;
use rdfInterface\QuadNoSubjectInterface;
use rdfInterface\MultipleQuadsMatchedException;
use termTemplates\QuadTemplate;

/**
 * Description of DatasetNode
 *
 * @author zozlak
 */
class DatasetNode implements DatasetNodeInterface {

    use \rdfHelpers\DatasetGettersTrait;

    public static function factory(TermInterface | null $node = null,
                                   QuadIteratorInterface | QuadIteratorAggregateInterface | null $quads = null): DatasetNodeInterface {
        if ($node === null) {
            throw new BadMethodCallException('$node parameter has to be provided');
        }
        return new DatasetNode($node, $quads);
    }

    private DatasetInterface $dataset;
    private TermInterface $node;

    public function __construct(TermInterface $node,
                                QuadIteratorInterface | QuadIteratorAggregateInterface | null $quads = null,
                                bool $indexed = true) {
        $this->node    = $node;
        $this->dataset = new Dataset($indexed);
        if ($quads !== null) {
            $this->dataset->add($quads);
        }
    }

    public function __toString(): string {
        $ret = '';
        foreach ($this->getIterator(new QuadTemplate($this->node)) as $i) {
            $ret .= $i . "\n";
        }
        return $ret;
    }

    public function withDataset(DatasetInterface $dataset): DatasetNode {
        $datasetNode          = new DatasetNode($this->node);
        $datasetNode->dataset = $dataset;
        return $datasetNode;
    }

    public function withNode(TermInterface $node): DatasetNode {
        $datasetNode          = new DatasetNode($node);
        $datasetNode->dataset = $this->dataset;
        return $datasetNode;
    }

    /**
     * 
     * @param QuadInterface|QuadNoSubjectInterface|Traversable<QuadInterface|QuadNoSubjectInterface>|array<QuadInterface|QuadNoSubjectInterface> $quads
     * @throws InvalidArgumentException
     * @return void
     */
    public function add(QuadInterface | QuadNoSubjectInterface | Traversable | array $quads): void {
        if ($quads instanceof QuadInterface) {
            $this->dataset->add($quads);
        } elseif ($quads instanceof QuadNoSubjectInterface) {
            $this->dataset->add([DataFactory::quad($this->node, $quads->getPredicate(), $quads->getObject(), $quads->getGraph())]);
        } else {
            $tmp = [];
            foreach ($quads as $i) {
                if ($i instanceof QuadNoSubjectInterface) {
                    $i = DataFactory::quad($this->node, $i->getPredicate(), $i->getObject(), $i->getGraph());
                }
                $tmp[] = $i;
            }
            $this->dataset->add($tmp);
        }
    }

    public function copy(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null,
                         bool $indexed = false): self {
        $dataset = $this->copyOther($indexed);
        $dataset->add($this->copyNode()->copy($filter));
        return $this->withDataset($dataset);
    }

    public function copyExcept(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter,
                               bool $indexed = false): self {
        $dataset = $this->copyOther($indexed);
        $dataset->add($this->copyNode()->copyExcept($filter));
        return $this->withDataset($dataset);
    }

    public function count(): int {
        return $this->copyNode()->count();
    }

    public function delete(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter,
                           bool $indexed = false): Dataset {
        $toDelete = $this->copyNode()->delete($filter, $indexed);
        $this->dataset->delete($toDelete);
        return $toDelete;
    }

    public function deleteExcept(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter,
                                 bool $indexed = false): Dataset {
        $toDelete = $this->copyNode()->deleteExcept($filter, $indexed);
        $this->dataset->delete($toDelete);
        return $toDelete;
    }

    public function equals(DatasetInterface | TermCompareInterface | DatasetNodeInterface $other): bool {
        $local = $this->copyNode();
        $tmpl  = new QuadTemplate($this->getNode());
        if ($other instanceof DatasetNodeInterface && !$this->getNode()->equals($other->getNode())) {
            return false;
        } elseif ($other instanceof DatasetNodeInterface || $other instanceof DatasetInterface) {
            return $local->equals($other->copy($tmpl));
        } elseif ($other instanceof TermCompareInterface) {
            return $other->equals($this->getNode());
        } else {
            throw new BadMethodCallException("Unsupported parameter type");
        }
    }

    public function forEach(callable $fn,
                            QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter = null): void {
        $node         = $this->getNode();
        $restrictedFn = fn(QuadInterface $quad, DatasetInterface $dataset) => $node->equals($quad->getSubject()) ? $fn($quad, $dataset) : $quad;
        $this->dataset->forEach($restrictedFn, $filter);
    }

    public function getDataset(): DatasetInterface {
        return $this->dataset;
    }

    public function getIterator(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): QuadIteratorInterface {
        return $this->copyNode()->getIterator($filter);
    }

    public function getNode(): TermInterface {
        return $this->node;
    }

    public function getValue(): mixed {
        return $this->node->getValue();
    }

    /**
     * 
     * @param QuadCompareInterface|callable|int<0, 0> $offset
     * @return bool
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetExists($offset): bool {
        return $this->copyNode()->offsetExists($offset);
    }

    /**
     * 
     * @param QuadCompareInterface|callable|int<0, 0> $offset
     * @return QuadInterface
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetGet($offset): QuadInterface {
        return $this->copyNode()->offsetGet($offset);
    }

    /**
     * 
     * @param QuadCompareInterface|callable|null $offset
     * @param QuadInterface $value
     * @return void
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetSet($offset, $value): void {
        if ($offset !== null) {
            $match = $this->offsetGet($offset);
            if ($match !== $value) {
                $this->dataset->delete($match);
            }
        }
        $this->dataset->add($value);
    }

    /**
     * 
     * @param QuadCompareInterface|callable $offset
     * @return void
     * @throws UnexpectedValueException
     * @throws MultipleQuadsMatchedException
     */
    public function offsetUnset($offset): void {
        if ($offset === 0) {
            throw new UnexpectedValueException();
        }
        if ($this->offsetExists($offset)) {
            $this->dataset->delete($offset);
        }
    }

    public function union(QuadInterface | QuadIteratorInterface | QuadIteratorAggregateInterface $other,
                          bool $indexed = false): self {
        $dataset = new Dataset($indexed);
        $dataset->add($this->dataset);
        if ($other instanceof QuadInterface) {
            $other = [$other];
        }
        foreach ($other as $i) {
            if ($this->node->equals($i->getSubject())) {
                $dataset->add($i);
            }
        }
        return new DatasetNode($this->node, $dataset);
    }

    public function xor(QuadInterface | QuadIteratorInterface | QuadIteratorAggregateInterface $other,
                        bool $indexed = false): self {
        $ret = $this->union($other, $indexed);
        $ret->delete($this->copy($other));
        return $ret;
    }

    public function any(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter): bool {
        return $this->copyNode()->any($filter);
    }

    public function every(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter): bool {
        return $this->copyNode()->every($filter);
    }

    public function none(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable $filter): bool {
        return $this->copyNode()->none($filter);
    }

    public function listGraphs(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->copyNode()->listGraphs($filter);
    }

    public function listObjects(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->copyNode()->listObjects($filter);
    }

    public function listPredicates(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->copyNode()->listPredicates($filter);
    }

    public function listSubjects(QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): TermIteratorInterface {
        return $this->copyNode()->listSubjects($filter);
    }

    public function map(callable $fn,
                        QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null,
                        bool $indexed = false): DatasetNodeInterface {
        $node        = $this->getNode();
        $datasetNode = new DatasetNode($node, null, $indexed);
        $dataset     = $datasetNode->getDataset();
        foreach ($this->dataset->getIterator($filter) as $i) {
            if ($node->equals($i->getSubject())) {
                $dataset->add($fn($i, $this));
            }
        }
        return $datasetNode;
    }

    public function reduce(callable $fn, mixed $initialValue = null,
                           QuadCompareInterface | QuadIteratorInterface | QuadIteratorAggregateInterface | callable | null $filter = null): mixed {
        return $this->copyNode()->reduce($fn, $initialValue, $filter);
    }

    private function copyOther(bool $indexed): Dataset {
        $dataset = new Dataset($indexed);
        foreach ($this->dataset as $i) {
            if (!$this->node->equals($i->getSubject())) {
                $dataset->add($i);
            }
        }
        return $dataset;
    }

    private function copyNode(bool $indexed = false): Dataset {
        $dataset = new Dataset($indexed);
        $dataset->add($this->dataset->getIterator(new QuadTemplate($this->node)));
        return $dataset;
    }
}

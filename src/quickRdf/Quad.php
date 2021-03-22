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

use BadMethodCallException;
use rdfInterface\NamedNode as iNamedNode;
use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\Literal as iLiteral;
use rdfInterface\DefaultGraph as iDefaultGraph;
use rdfInterface\Term as iTerm;
use rdfInterface\Quad as iQuad;
use quickRdf\DataFactory as DF;

/**
 * Description of Triple
 *
 * @author zozlak
 */
class Quad implements iQuad, SingletonTerm {

    private iTerm $subject;
    private iNamedNode $predicate;
    private iTerm $object;
    private iNamedNode | iBlankNode | iDefaultGraph $graph;

    public function __construct(
        iTerm $subject, iNamedNode $predicate, iTerm $object,
        iNamedNode | iBlankNode | iDefaultGraph | null $graph = null
    ) {
        (!DF::$enforceConstructor) || DF::checkCall();
        if ($subject instanceof iLiteral) {
            throw new BadMethodCallException("subject can't be a literal");
        }
        $this->subject   = $subject;
        $this->predicate = $predicate;
        $this->object    = $object;
        $this->graph     = $graph ?? DF::defaultGraph();
    }

    public function __toString(): string {
        $sbj   = (string) $this->subject;
        $pred  = (string) $this->predicate;
        $obj   = (string) $this->object;
        $graph = $this->graph instanceof iDefaultGraph ? '' : (string) $this->graph;
        if ($this->subject instanceof iQuad) {
            $sbj = "<< $sbj >>";
        }
        if ($this->object instanceof iQuad) {
            $obj = "<< $obj >>";
        }
        return rtrim("$sbj $pred $obj $graph");
    }

    public function equals(iTerm $term): bool {
        if ($term instanceof SingletonTerm) {
            return $this === $term;
        } else if ($term instanceof iQuad) {
            /* @var $term iQuad */
            return $this->subject->equals($term->getSubject()) &&
                $this->predicate->equals($term->getPredicate()) &&
                $this->object->equals($term->getObject()) &&
                $this->graph->equals($term->getGraph());
        } else {
            return false;
        }
    }

    public function getValue(): string {
        throw new \BadMethodCallException();
    }

    public function getSubject(): iTerm {
        return $this->subject;
    }

    public function getPredicate(): iNamedNode {
        return $this->predicate;
    }

    public function getObject(): iTerm {
        return $this->object;
    }

    public function getGraph(): iNamedNode | iBlankNode | iDefaultGraph {
        return $this->graph;
    }

    public function withSubject(iTerm $subject): iQuad {
        return DF::quad($subject, $this->predicate, $this->object, $this->graph);
    }

    public function withPredicate(iNamedNode $predicate): iQuad {
        return DF::quad($this->subject, $predicate, $this->object, $this->graph);
    }

    public function withObject(iTerm $object): iQuad {
        return DF::quad($this->subject, $this->predicate, $object, $this->graph);
    }

    public function withGraph(iNamedNode | iBlankNode | iDefaultGraph | null $graph): iQuad {
        return DF::quad($this->subject, $this->predicate, $this->object, $graph);
    }
}

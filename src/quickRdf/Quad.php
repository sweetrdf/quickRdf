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
class Quad implements iQuad, HashableTerm {

    private iTerm $subject;
    private iNamedNode $predicate;
    private iTerm $object;
    private iNamedNode | iBlankNode | iDefaultGraph $graphIri;

    public function __construct(
        iTerm $subject, iNamedNode $predicate, iTerm $object,
        iNamedNode | iBlankNode | iDefaultGraph | null $graphIri = null
    ) {
        (!DF::$enforceConstructor) || DF::checkCall();
        if ($subject instanceof iLiteral) {
            throw new BadMethodCallException("subject can't be a literal");
        }
        $this->subject   = $subject;
        $this->predicate = $predicate;
        $this->object    = $object;
        $this->graphIri  = $graphIri ?? DF::defaultGraph();
    }

    public function __toString(): string {
        return rtrim("$this->subject $this->predicate $this->object $this->graphIri");
    }

    public function getType(): string {
        return \rdfInterface\TYPE_QUAD;
    }

    public function equals(iTerm $term): bool {
        return $this === $term;
//        if ($term->getType() !== $this->getType()) {
//            return false;
//        }
//        /* @var $term iQuad */
//        return $this->subject->equals($term->getSubject()) &&
//            $this->predicate->equals($term->getPredicate()) &&
//            $this->object->equals($term->getObject()) &&
//            $this->graphIri->equals($term->getGraphIri());
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

    public function getGraphIri(): iNamedNode | iBlankNode | iDefaultGraph {
        return $this->graphIri;
    }

    public function withSubject(iTerm $subject): iQuad {
        return DF::quad($subject, $this->predicate, $this->object, $this->graphIri);
    }

    public function withPredicate(iNamedNode $predicate): iQuad {
        return DF::quad($this->subject, $predicate, $this->object, $this->graphIri);
    }

    public function withObject(iTerm $object): iQuad {
        return DF::quad($this->subject, $this->predicate, $object, $this->graphIri);
    }

    public function withGraphIri(iNamedNode | iBlankNode | iDefaultGraph | null $graphIri): iQuad {
        return DF::quad($this->subject, $this->predicate, $this->object, $graphIri);
    }
}

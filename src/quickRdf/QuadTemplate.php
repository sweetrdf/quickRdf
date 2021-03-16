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
use rdfInterface\QuadTemplate as iQuadTemplate;
use quickRdf\DataFactory as DF;

/**
 * Description of QuadTemplate
 *
 * @author zozlak
 */
class QuadTemplate implements iQuadTemplate
{

    /**
     *
     * @var iTerm|null
     */
    private iTerm | null $subject;

    /**
     *
     * @var iNamedNode|null
     */
    private iNamedNode | null $predicate;

    /**
     *
     * @var iTerm|null
     */
    private iTerm | null $object;

    /**
     *
     * @var iNamedNode|iBlankNode|null
     */
    private iNamedNode | iBlankNode | null $graphIri;

    public function __construct(
        iTerm | null $subject = null, iNamedNode | null $predicate = null,
        iTerm | null $object = null,
        iNamedNode | iBlankNode | iDefaultGraph | null $graphIri = null
    )
    {
        if ($subject === null && $predicate === null && $object === null && $graphIri === null) {
            throw new BadMethodCallException("At least one part of the quad has to be specified");
        }
        if ($subject instanceof iLiteral) {
            throw new BadMethodCallException("subject can't be a literal");
        }
        if ($graphIri instanceof iDefaultGraph) {
            // all triples belong to the default graph, so nothing to search for
            $graphIri = null;
        }
        $this->subject   = $subject;
        $this->predicate = $predicate;
        $this->object    = $object;
        $this->graphIri  = $graphIri;
    }

    public function __toString(): string
    {
        return rtrim("$this->subject $this->predicate $this->object $this->graphIri");
    }

    public function getType(): string
    {
        return \rdfInterface\TYPE_QUAD_TMPL;
    }

    public function equals(\rdfInterface\Term $term): bool
    {
        if ($term instanceof iQuadTemplate) {
            /* @var $term iQuad */
            return $this->subject === $term->getSubject() &&
                $this->predicate === $term->getPredicate() &&
                $this->object === $term->getObject() &&
                $this->graphIri === $term->getGraphIri();
        } else if ($term instanceof iQuad) {
            /* @var $term iQuad */
            return ($this->subject === null || $this->subject->equals($term->getSubject())) &&
                ($this->predicate === null || $this->predicate->equals($term->getPredicate())) &&
                ($this->object === null || $this->object->equals($term->getObject())) &&
                ($this->graphIri === null || $this->graphIri->equals($term->getGraphIri()));
        }
        return false;
    }

    public function getValue(): string
    {
        throw new \BadMethodCallException();
    }

    public function getSubject(): iTerm | null
    {
        return $this->subject;
    }

    public function getPredicate(): iNamedNode | null
    {
        return $this->predicate;
    }

    public function getObject(): iTerm | null
    {
        return $this->object;
    }

    public function getGraphIri(): iNamedNode | iBlankNode | null
    {
        return $this->graphIri;
    }

    public function withSubject(iTerm | null $subject): iQuadTemplate
    {
        return DF::quadTemplate($subject, $this->predicate, $this->object, $this->graphIri);
    }

    public function withPredicate(iNamedNode | null $predicate): iQuadTemplate
    {
        return DF::quadTemplate($this->subject, $predicate, $this->object, $this->graphIri);
    }

    public function withObject(iTerm | null $object): iQuadTemplate
    {
        return DF::quadTemplate($this->subject, $this->predicate, $object, $this->graphIri);
    }

    public function withGraphIri(iNamedNode | iBlankNode | iDefaultGraph | null $graphIri): iQuadTemplate
    {
        return DF::quadTemplate($this->subject, $this->predicate, $this->object, $graphIri);
    }
}

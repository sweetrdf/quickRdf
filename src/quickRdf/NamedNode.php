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

use rdfInterface\NamedNodeInterface;
use rdfInterface\TermCompareInterface;
use quickRdf\DataFactory as DF;

/**
 * Description of Iri
 *
 * @author zozlak
 */
class NamedNode implements NamedNodeInterface, SingletonTerm {

    private string $iri;

    public function __construct(string $iri) {
        (!DF::$enforceConstructor) || DF::checkCall();
        $this->iri = $iri;
    }

    public function __toString(): string {
        return $this->iri;
    }

    public function getValue(): string {
        return $this->iri;
    }

    public function equals(TermCompareInterface $term): bool {
        if ($term instanceof SingletonTerm) {
            return $this === $term;
        } else {
            return $term instanceof NamedNodeInterface && $this->getValue() === $term->getValue();
        }
    }
}

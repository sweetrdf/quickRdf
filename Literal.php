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

use zozlak\RdfConstants as RDF;
use dumbrdf\DataFactory as DF;

/**
 * Description of Literal
 *
 * @author zozlak
 */
class Literal implements \rdfInterface\Literal {

    /**
     *
     * @var string
     */
    private $value;

    /**
     *
     * @var string
     */
    private $lang;

    /**
     *
     * @var string
     */
    private $datatype;

    public function __construct(string $value, ?string $lang = null,
                                ?string $datatype = null) {
        (!DF::$enforceConstructor) || DF::checkCall();
        $this->value    = $value;
        $this->lang     = $lang;
        $this->datatype = $datatype;
    }

    public function __toString(): string {
        if (!empty($this->lang)) {
            $langtype = "@" . $this->lang;
        } elseif (!empty($this->datatype)) {
            $langtype = "^^<$this->datatype>";
        }
        return '"' . $this->value . '"' . $langtype;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getLang(): ?string {
        return $this->lang;
    }

    public function getDatatype(): \rdfInterface\NamedNode {
        return DF::namedNode($this->datatype ?? RDF::XSD_STRING);
    }

    public function getType(): string {
        return \rdfInterface\TYPE_LITERAL;
    }

    public function equals(\rdfInterface\Term $term): bool {
        return $term instanceof \rdfInterface\Literal &&
            $this->value === $term->value &&
            $this->lang === $term->lang &&
            $this->datatype === $term->datatype;
    }

    public function withValue(string $value): \rdfInterface\Literal {
        return DF::literal($value, $this->lang, $this->datatype);
    }

    public function withLang(?string $lang): \rdfInterface\Literal {
        return DF::literal($this->value, $lang, $this->datatype);
    }

    public function withDatatype(?string $datatype): \rdfInterface\Literal {
        return DF::literal($this->value, $this->lang, $datatype);
    }

}

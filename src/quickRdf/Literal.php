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
use Stringable;
use zozlak\RdfConstants as RDF;
use rdfInterface\Literal as iLiteral;
use quickRdf\DataFactory as DF;

/**
 * Description of Literal
 *
 * @author zozlak
 */
class Literal implements \rdfInterface\Literal, SingletonTerm {

    /**
     *
     * @var int | float | string | bool | Stringable
     */
    private $value;

    /**
     *
     * @var string|null
     */
    private $lang;

    /**
     *
     * @var string
     */
    private $datatype;

    public function __construct(
        int | float | string | bool | Stringable $value, ?string $lang = null,
        ?string $datatype = null
    ) {
        (!DF::$enforceConstructor) || DF::checkCall();
        // just trust passed values, they should be sanitized by the DataFactory anyway
        $this->value    = $value;
        $this->lang     = $lang;
        $this->datatype = $datatype;
    }

    public function __toString(): string {
        $langtype = '';
        if (!empty($this->lang)) {
            $langtype = "@" . $this->lang;
        } elseif ($this->datatype !== RDF::XSD_STRING) {
            $langtype = "^^<$this->datatype>";
        }
        return '"' . $this->value . '"' . $langtype;
    }

    public function getValue(int $cast = self::CAST_LEXICAL_FORM): mixed {
        switch ($cast) {
            case self::CAST_LEXICAL_FORM:
                return (string) $this->value;
            case self::CAST_NONE:
                return $this->value;
            default:
                throw new BadMethodCallException("Unsupported cast requested");
        }
    }

    public function getLang(): ?string {
        return $this->lang;
    }

    public function getDatatype(): string {
        return $this->datatype;
    }

    public function equals(\rdfInterface\Term $term): bool {
        if ($term instanceof iLiteral) {
            return $this === $term ||
                $this->getValue(self::CAST_LEXICAL_FORM) === $term->getValue(self::CAST_LEXICAL_FORM) &&
                $this->getLang() === $term->getLang() &&
                $this->getDatatype() === $term->getDatatype();
        } else {
            return false;
        }
    }

    public function withValue(int | float | string | bool | Stringable $value): \rdfInterface\Literal {
        $lang     = $datatype = null;
        if (is_string($value) || $value instanceof Stringable) {
            $lang     = $this->lang;
            $datatype = $this->datatype;
        }
        return DF::literal($value, $lang, $datatype);
    }

    public function withLang(?string $lang): \rdfInterface\Literal {
        $hadLang = $this->lang !== null;
        $hasLang = !empty($lang);
        if ($hadLang !== $hasLang) {
            $datatype = $hasLang ? RDF::RDF_LANG_STRING : RDF::XSD_STRING;
        } else {
            $datatype = $this->datatype;
        }
        return DF::literal($this->value, $lang, $datatype);
    }

    public function withDatatype(string $datatype): \rdfInterface\Literal {
        if (empty($datatype) || $datatype === RDF::RDF_LANG_STRING) {
            throw new BadMethodCallException("Datatype can't be empty nor rdf:langString");
        }
        return DF::literal($this->value, null, $datatype);
    }
}

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

use Stringable;
use WeakReference;
use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\NamedNode as iNamedNode;
use rdfInterface\Literal as iLiteral;
use rdfInterface\DefaultGraph as iDefaultGraph;
use rdfInterface\Quad as iQuad;
use rdfInterface\QuadTemplate as iQuadTemplate;

/**
 * Description of DataFactory
 *
 * @author zozlak
 */
class DataFactory implements \rdfInterface\DataFactory {

    static private $objects;

    static private function init(): void {
        if (self::$objects === null) {
            self::$objects = [];
            $types         = [
                \rdfInterface\TYPE_NAMED_NODE,
                \rdfInterface\TYPE_BLANK_NODE,
                \rdfInterface\TYPE_LITERAL,
                \rdfInterface\TYPE_DEFAULT_GRAPH,
            ];
            foreach ($types as $i) {
                self::$objects = [];
            }
        }
    }

    static public function blankNode(string|Stringable|null $iri = null): iBlankNode {
        self::init();
        $a   = &self::$objects[\rdfInterface\TYPE_BLANK_NODE];
        $iri = $iri === null ? $iri : (string) $iri;
        if ($iri === null || !isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new BlankNode($iri);
            $iri     = $obj->getValue();
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get();
    }

    static public function namedNode(string|Stringable $iri): iNamedNode {
        self::init();
        $iri = (string) $iri;
        $a   = &self::$objects[\rdfInterface\TYPE_NAMED_NODE];
        if (!isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new NamedNode($iri);
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get();
    }

    static public function defaultGraph(string|Stringable|null $iri = null): iDefaultGraph {
        self::init();
        $iri = $iri === null ? $iri : (string) $iri;
        $a   = &self::$objects[\rdfInterface\TYPE_DEFAULT_GRAPH];
        if ($iri === null || !isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new \rdfHelpers\DefaultGraph($iri);
            $iri     = $obj->getValue();
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get();
    }

    static public function literal(string|Stringable $value,
                                   string|Stringable $lang,
                                   string|Stringable $datatype): iLiteral {
        self::init();
        $value    = (string) $value;
        $lang     = Literal::sanitizeLang((string) $lang);
        $datatype = Literal::sanitizeDatatype((string) $datatype);
        $a        = &self::$objects[\rdfInterface\TYPE_LITERAL];
        $hash     = $lang . $datatype . "\n" . str_replace("\n", "\\n", $value);
        if (!isset($a[$hash]) || $a[$hash]->get() === null) {
            $obj      = new Literal($value, $lang, $datatype);
            $a[$hash] = WeakReference::create($obj);
        }
        return $a[$hash]->get();
    }

    static public function quad(iNamedNode|iBlankNode|iQuad $subject,
                                iNamedNode $predicate,
                                iNamedNode|iBlankNode|iLiteral|iQuad $object,
                                iNamedNode|iBlankNode|null $graphIri = null): iQuad {
        return new Quad($subject, $predicate, $object, $graphIri);
    }

    static public function quadTemplate(iNamedNode|iBlankNode|iQuad|null $subject = null,
                                        iNamedNode|null $predicate = null,
                                        iNamedNode|iBlankNode|iLiteral|iQuad|null $object = null,
                                        iNamedNode|iBlankNode|null $graphIri = null): iQuadTemplate {
        return new QuadTemplate($subject, $predicate, $object, $graphIri);
    }

    static public function variable(string|Stringable $name): \rdfInterface\Variable {
        
    }

}

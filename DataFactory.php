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
use rdfHelpers\DefaultGraph;
use rdfInterface\Term as iTerm;
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
                \rdfInterface\TYPE_QUAD,
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
        $hash     = self::hashLiteral($value, $lang, $datatype);
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
        self::init();
        $graphIri ??= new DefaultGraph();
        $hash     = self::hashQuad($subject, $predicate, $object, $graphIri);
        $a        = &self::$objects[\rdfInterface\TYPE_QUAD];
        if (!isset($a[$hash]) || $a[$hash]->get() === null) {
            $obj      = new Quad($subject, $predicate, $object, $graphIri);
            $a[$hash] = WeakReference::create($obj);
        }
        return $a[$hash]->get();
    }

    static public function quadTemplate(iNamedNode|iBlankNode|iQuad|null $subject = null,
                                        iNamedNode|null $predicate = null,
                                        iNamedNode|iBlankNode|iLiteral|iQuad|null $object = null,
                                        iNamedNode|iBlankNode|null $graphIri = null): iQuadTemplate {
        self::init();
        $hash = self::hashQuad($subject, $predicate, $object, $graphIri);
        $a    = &self::$objects[\rdfInterface\TYPE_QUAD_TMPL];
        if (!isset($a[$hash]) || $a[$hash]->get() === null) {
            $obj      = new QuadTemplate($subject, $predicate, $object, $graphIri);
            $a[$hash] = WeakReference::create($obj);
        }
        return $a[$hash]->get();
    }

    static public function variable(string|Stringable $name): \rdfInterface\Variable {
        throw new RdfException('Variables are not implemented');
    }

    static private function hashTerm(iTerm|null $t): string|null {
        if ($t === null) {
            return null;
        }
        switch ($t->getType()) {
            case \rdfInterface\TYPE_BLANK_NODE:
            case \rdfInterface\TYPE_NAMED_NODE:
            case \rdfInterface\TYPE_DEFAULT_GRAPH:
                return $t->getValue();
            case \rdfInterface\TYPE_LITERAL:
                return self::hashLiteral($t->getValue(), $t->getLang(), $t->getDatatype());
            case \rdfInterface\TYPE_QUAD:
            case \rdfInterface\TYPE_QUAD_TMPL:
                $sbj   = self::hashTerm($t->getSubject());
                $pred  = self::hashTerm($t->getPredicate());
                $obj   = self::hashTerm($t->getObject());
                $graph = self::hashTerm($t->getGraph());
                return "$sbj\n$pred\n$obj\n$graph";
            default:
                throw new RdfException("Can't hash Term of type " . $t->getType());
        }
    }

    static private function hashLiteral(string $value, string|null $lang,
                                        string|null $datatype): string {
        return $lang . $datatype . "\n" . str_replace("\n", "\\n", $value);
    }

    static private function hashQuad(iNamedNode|iBlankNode|iQuad|null $subject = null,
                                     iNamedNode|null $predicate = null,
                                     iNamedNode|iBlankNode|iLiteral|iQuad|null $object = null,
                                     iNamedNode|iBlankNode|null $graphIri = null): string {
        return self::hashTerm($subject) . "\n" . self::hashTerm($predicate) . "\n" . self::hashTerm($object) . "\n" . self::hashTerm($graphIri);
    }

}

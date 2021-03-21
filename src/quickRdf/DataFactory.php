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

use Stringable;
use WeakReference;
use zozlak\RdfConstants as RDF;
use rdfInterface\Term as iTerm;
use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\NamedNode as iNamedNode;
use rdfInterface\Literal as iLiteral;
use rdfInterface\DefaultGraph as iDefaultGraph;
use rdfInterface\Quad as iQuad;
use rdfInterface\QuadTemplate as iQuadTemplate;
use rdfHelpers\DefaultGraph;

/**
 * Description of DataFactory
 *
 * @author zozlak
 */
class DataFactory implements \rdfInterface\DataFactory {

    /**
     *
     * @var array<string, WeakReference<BlankNode>>
     */
    private static array $blankNodes = [];

    /**
     *
     * @var array<string, WeakReference<NamedNode>>
     */
    private static array $namedNodes = [];

    /**
     *
     * @var array<string, WeakReference<Literal>>
     */
    private static array $literals     = [];
    private static iDefaultGraph | null $defaultGraph = null;

    /**
     *
     * @var array<string, WeakReference<Quad>>
     */
    private static array $quads             = [];
    public static bool $enforceConstructor = true;

    public static function blankNode(string | Stringable | null $iri = null): iBlankNode {
        $a   = &self::$blankNodes;
        $iri = $iri === null ? $iri : (string) $iri;
        if ($iri === null || !isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new BlankNode($iri);
            $iri     = $obj->getValue();
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get() ?? throw new RuntimeException("Object creation failed");
    }

    public static function namedNode(string | Stringable $iri): iNamedNode {
        $iri = (string) $iri;
        $a   = &self::$namedNodes;
        if (!isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new NamedNode($iri);
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get() ?? throw new RuntimeException("Object creation failed");
    }

    public static function defaultGraph(): iDefaultGraph {
        if (self::$defaultGraph === null) {
            self::$defaultGraph = new DefaultGraph();
        }
        return self::$defaultGraph;
    }

    public static function literal(
        int | float | string | bool | Stringable $value,
        string | Stringable | null $lang = null,
        string | Stringable | null $datatype = null
    ): iLiteral {
        if (!empty($lang)) {
            $datatype = RDF::RDF_LANG_STRING;
        } elseif (empty($datatype)) {
            $lang = null;
            switch (gettype($value)) {
                case 'integer':
                    $datatype = RDF::XSD_INTEGER;
                    break;
                case 'double':
                    $datatype = RDF::XSD_DECIMAL;
                    break;
                case 'boolean':
                    $datatype = RDF::XSD_BOOLEAN;
                    $value    = (int) ((string) $value);
                    break;
                default:
                    $datatype = RDF::XSD_STRING;
            }
        } else {
            $lang = null;
        }

        $hash = self::hashLiteral((string) $value, $lang, $datatype);
        $a    = &self::$literals;
        if (!isset($a[$hash]) || $a[$hash]->get() === null) {
            $obj      = new Literal($value, $lang, $datatype);
            $a[$hash] = WeakReference::create($obj);
        }
        return $a[$hash]->get() ?? throw new RuntimeException("Object creation failed");
    }

    public static function quad(
        iTerm $subject, iNamedNode $predicate, iTerm $object,
        iNamedNode | iBlankNode | iDefaultGraph | null $graphIri = null
    ): iQuad {
        $graphIri ??= self::defaultGraph();
        $hash     = self::hashQuad($subject, $predicate, $object, $graphIri);
        $a        = &self::$quads;
        if (!isset($a[$hash]) || $a[$hash]->get() === null) {
            $obj      = new Quad($subject, $predicate, $object, $graphIri);
            $a[$hash] = WeakReference::create($obj);
        }
        return $a[$hash]->get() ?? throw new RuntimeException("Object creation failed");
    }

    public static function quadTemplate(
        iTerm | null $subject = null, iNamedNode | null $predicate = null,
        iTerm | null $object = null,
        iNamedNode | iBlankNode | iDefaultGraph | null $graphIri = null
    ): iQuadTemplate {
        return new QuadTemplate($subject, $predicate, $object, $graphIri);
    }

    public static function variable(string | Stringable $name): \rdfInterface\Variable {
        throw new RdfException('Variables are not implemented');
    }

    public static function importTerm(iTerm $term, bool $recursive = true): iTerm {
        if ($term instanceof iLiteral) {
            return self::literal($term->getValue(), $term->getLang(), $term->getDatatype());
        } elseif ($term instanceof iBlankNode) {
            return self::blankNode($term->getValue());
        } elseif ($term instanceof iNamedNode) {
            return self::namedNode($term->getValue());
        } elseif ($term instanceof iDefaultGraph) {
            return self::defaultGraph();
        } elseif ($term instanceof iQuad) {
            $sbj   = $term->getSubject();
            $pred  = $term->getPredicate();
            $obj   = $term->getObject();
            $graph = $term->getGraphIri();
            if ($recursive) {
                $sbj   = self::importTerm($sbj, $recursive);
                $pred  = self::importTerm($pred, $recursive);
                $obj   = self::importTerm($obj, $recursive);
                $graph = self::importTerm($graph, $recursive);
            }
            return self::quad($sbj, $pred, $obj, $graph);
        } else {
            throw new RdfException("Can't import term of class " . $term::class);
        }
    }

    /**
     * Wrapper for importTerm() to make phpstan happy.
     * 
     * @param iQuad $quad
     * @return iQuad
     */
    public static function importQuad(iQuad $quad): iQuad {
        return self::importTerm($quad);
    }

    public static function checkCall(): bool {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if (count($trace) < 3 || ($trace[2]['class'] ?? '') !== self::class) {
            $c1 = $trace[1]['class'] ?? '';
            $c2 = self::class;
            throw new RdfException("$c1 constructor can't be called directly. Use the $c2 class instead.");
        }
        return true;
    }

    private static function hashTerm(iTerm $t): string {
        $sep = chr(1);
        if ($t instanceof iDefaultGraph) {
            return '';
        } elseif ($t instanceof iBlankNode || $t instanceof iNamedNode) {
            return (string) $t->getValue();
        } elseif ($t instanceof iLiteral) {
            return self::hashLiteral($t->getValue(), $t->getLang(), $t->getDatatype());
        } elseif ($t instanceof iQuad) {
            return self::hashQuad($t->getSubject(), $t->getPredicate(), $t->getObject(), $t->getGraphIri());
        } else {
            throw new RdfException("Can't hash Term of class " . $t::class);
        }
    }

    private static function hashLiteral(string $value, ?string $lang,
                                        string $datatype): string {
        $sep = chr(1);
        return $datatype . $sep . $lang . $sep . $value;
    }

    private static function hashQuad(iTerm $subject, iTerm $predicate,
                                     iTerm $object, iTerm $graphIri): string {
        $sep = chr(1);
        return self::hashTerm($subject) . $sep . self::hashTerm($predicate) . $sep .
            self::hashTerm($object) . $sep . self::hashTerm($graphIri);
    }

    /**
     *
     * @return array<\stdClass>
     */
    public static function getCacheCounts(): array {
        $ret = [];
        $map = [
            BlankNode::class => 'blankNodes',
            NamedNode::class => 'namedNodes',
            Literal::class   => 'literals',
            Quad::class      => 'quads',
        ];
        foreach ($map as $k => $v) {
            $ret[$k] = (object) ['total' => count(self::${$v}), 'valid' => 0];
            foreach (self::${$v} as $i) {
                $ret[$k]->valid += $i->get() !== null;
            }
        }
        return $ret;
    }
}

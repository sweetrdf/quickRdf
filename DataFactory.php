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
use zozlak\RdfConstants as RDF;
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
class DataFactory implements \rdfInterface\DataFactory
{

    private static $objects;
    public static $enforceConstructor = true;

    public static function init(): void
    {
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
                self::$objects[$i] = [];
            }
        }
    }

    public static function blankNode(string | Stringable | null $iri = null): iBlankNode
    {
        $a   = &self::$objects[\rdfInterface\TYPE_BLANK_NODE];
        $iri = $iri === null ? $iri : (string) $iri;
        if ($iri === null || !isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new BlankNode($iri);
            $iri     = $obj->getValue();
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get();
    }

    public static function namedNode(string | Stringable $iri): iNamedNode
    {
        $iri = (string) $iri;
        $a   = &self::$objects[\rdfInterface\TYPE_NAMED_NODE];
        if (!isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new NamedNode($iri);
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get();
    }

    public static function defaultGraph(string | Stringable | null $iri = null): iDefaultGraph
    {
        $iri = $iri === null ? $iri : (string) $iri;
        $a   = &self::$objects[\rdfInterface\TYPE_DEFAULT_GRAPH];
        if ($iri === null || !isset($a[$iri]) || $a[$iri]->get() === null) {
            $obj     = new DefaultGraph($iri);
            $iri     = $obj->getValue();
            $a[$iri] = WeakReference::create($obj);
        }
        return $a[$iri]->get();
    }

    public static function literal(
        string | Stringable $value,
        string | Stringable $lang = null,
        string | Stringable $datatype = null
    ): iLiteral {

        $value    = (string) $value;
        $lang     = self::sanitizeLang((string) $lang);
        $datatype = self::sanitizeDatatype((string) $datatype);
        self::checkLangDatatype($lang, $datatype);

        $hash = self::hashLiteral($value, $lang, $datatype);
        $a    = &self::$objects[\rdfInterface\TYPE_LITERAL];
        if (!isset($a[$hash]) || $a[$hash]->get() === null) {
            $obj      = new Literal($value, $lang, $datatype);
            $a[$hash] = WeakReference::create($obj);
        }
        return $a[$hash]->get();
    }

    public static function quad(
        iTerm $subject,
        iNamedNode $predicate,
        iTerm $object,
        iNamedNode | iBlankNode | null $graphIri = null
    ): iQuad {
        $graphIri ??= new DefaultGraph();
        $hash     = self::hashQuad($subject, $predicate, $object, $graphIri);
        $a        = &self::$objects[\rdfInterface\TYPE_QUAD];
        if (!isset($a[$hash]) || $a[$hash]->get() === null) {
            $obj      = new Quad($subject, $predicate, $object, $graphIri);
            $a[$hash] = WeakReference::create($obj);
        }
        return $a[$hash]->get();
    }

    public static function quadTemplate(
        iTerm | null $subject = null,
        iNamedNode | null $predicate = null,
        iTerm | null $object = null,
        iNamedNode | iBlankNode | null $graphIri = null
    ): iQuadTemplate {
        return new QuadTemplate($subject, $predicate, $object, $graphIri);
    }

    public static function variable(string | Stringable $name): \rdfInterface\Variable
    {
        throw new RdfException('Variables are not implemented');
    }

    public static function checkCall(): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if (count($trace) < 3 || ($trace[2]['class'] ?? '') !== self::class) {
            $c1 = $trace[1]['class'] ?? '';
            $c2 = self::class;
            throw new RdfException("$c constructor can't be called directly. Use the $c2 class instead.");
        }
    }

    private static function hashTerm(iTerm | null $t): string | null
    {
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

    private static function hashLiteral(
        string $value,
        string | null $lang,
        string | null $datatype
    ): string {
        return $lang . $datatype . "\n" . str_replace("\n", "\\n", $value);
    }

    private static function hashQuad(
        iNamedNode | iBlankNode | iQuad | null $subject = null,
        iNamedNode | null $predicate = null,
        iNamedNode | iBlankNode | iLiteral | iQuad | null $object = null,
        iNamedNode | iBlankNode | null $graphIri = null
    ): string {
        return self::hashTerm($subject) . "\n" . self::hashTerm($predicate) . "\n" .
            self::hashTerm($object) . "\n" . self::hashTerm($graphIri);
    }

    private static function checkLangDatatype(?string $lang, ?string $datatype): void
    {
        if ($lang !== null && $datatype !== null) {
            throw new RdfException('Literal with both lang and type');
        }
    }

    private static function sanitizeLang(?string $lang): ?string
    {
        return empty($lang) ? null : $lang;
    }

    private static function sanitizeDatatype(?string $datatype): ?string
    {
        return empty($datatype) || $datatype === RDF::XSD_STRING ? null : $datatype;
    }

    /**
     *
     * @return array
     */
    public static function getCacheCounts(): array
    {
        $ret = [];
        foreach (self::$objects as $k => &$v) {
            $ret[$k] = (object) ['total' => count($v), 'valid' => 0];
            foreach ($v as $i) {
                $ret[$k]->valid += $i->get() !== null;
            }
        }
        return $ret;
    }
}

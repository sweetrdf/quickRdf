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

        $lang     = self::sanitizeLang((string) $lang);
        $datatype = self::sanitizeDatatype((string) $datatype);
        self::checkLangDatatype($lang, $datatype);

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

    public static function checkCall(): bool {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if (count($trace) < 3 || ($trace[2]['class'] ?? '') !== self::class) {
            $c1 = $trace[1]['class'] ?? '';
            $c2 = self::class;
            throw new RdfException("$c1 constructor can't be called directly. Use the $c2 class instead.");
        }
        return true;
    }

    private static function hashTerm(iTerm | null $t): string | null {
        if ($t === null) {
            return null;
        }
        if ($t instanceof iDefaultGraph) {
            return '';
        } elseif ($t instanceof iBlankNode || $t instanceof iNamedNode) {
            return (string) $t->getValue();
        } elseif ($t instanceof iLiteral) {
            return self::hashLiteral((string) $t->getValue(), $t->getLang(), $t->getDatatype());
        } elseif ($t instanceof iQuad || $t instanceof iQuadTemplate) {
            $sbj   = self::hashTerm($t->getSubject());
            $pred  = self::hashTerm($t->getPredicate());
            $obj   = self::hashTerm($t->getObject());
            $graph = self::hashTerm($t->getGraphIri());
            return "$sbj\n$pred\n$obj\n$graph";
        } else {
            throw new RdfException("Can't hash Term of type " . $t->getType());
        }
    }

    private static function hashLiteral(
        string $value, string | null $lang, string | null $datatype
    ): string {
        return $lang . $datatype . "\n" . str_replace("\n", "\\n", $value);
    }

    private static function hashQuad(
        iTerm | null $subject = null, iNamedNode | null $predicate = null,
        iTerm | null $object = null,
        iNamedNode | iBlankNode | iDefaultGraph $graphIri = null
    ): string {
        return self::hashTerm($subject) . "\n" . self::hashTerm($predicate) . "\n" .
            self::hashTerm($object) . "\n" . self::hashTerm($graphIri);
    }

    private static function checkLangDatatype(?string $lang, ?string $datatype): void {
        if ($lang !== null && $datatype !== null) {
            throw new RdfException('Literal with both lang and type');
        }
    }

    private static function sanitizeLang(?string $lang): ?string {
        return empty($lang) ? null : $lang;
    }

    private static function sanitizeDatatype(?string $datatype): ?string {
        return empty($datatype) || $datatype === RDF::XSD_STRING ? null : $datatype;
    }

    /**
     *
     * @return array<\stdClass>
     */
    public static function getCacheCounts(): array {
        $ret = [];
        $map = [
            \rdfInterface\TYPE_BLANK_NODE => 'blankNodes',
            \rdfInterface\TYPE_NAMED_NODE => 'namedNodes',
            \rdfInterface\TYPE_LITERAL    => 'literals',
            \rdfInterface\TYPE_QUAD       => 'quads',
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

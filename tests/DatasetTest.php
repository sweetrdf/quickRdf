<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace quickRdf;

use OutOfBoundsException;
use rdfHelpers\GenericQuadIterator;
use rdfInterface\Literal as iLiteral;
use rdfInterface\Quad as iQuad;
use rdfInterface\Dataset as iDataset;
use quickRdf\DataFactory as DF;

/**
 * Description of LoggerTest
 *
 * @author zozlak
 */
class DatasetTest extends \PHPUnit\Framework\TestCase {

    /**
     *
     * @var iQuad[]
     */
    private static $quads;

    public static function setUpBeforeClass(): void {
        self::$quads = [
            DF::quad(DF::namedNode('foo'), DF::namedNode('bar'), DF::literal('baz')),
            DF::quad(DF::namedNode('baz'), DF::namedNode('foo'), DF::namedNode('bar')),
            DF::quad(DF::namedNode('bar'), DF::namedNode('baz'), DF::namedNode('foo')),
            DF::quad(DF::namedNode('foo'), DF::namedNode('bar'), DF::literal('baz', 'en'), DF::namedNode('graph')),
        ];
    }

    public function testAddQuads(): void {
        $d = new Dataset();
        for ($i = 0; $i + 1 < count(self::$quads); $i++) {
            $d->add(self::$quads[$i]);
        }
        $this->assertEquals(3, count($d));

        $d->add(new GenericQuadIterator(self::$quads));
        $this->assertEquals(4, count($d));
    }

    public function testIterator(): void {
        $d = new Dataset();
        $d->add(new GenericQuadIterator(self::$quads));
        foreach ($d as $k => $v) {
            $this->assertTrue($v->equals(self::$quads[$k]));
        }
    }

    public function testOffsetGetSmall(): void {
        $d      = new Dataset();
        $d->add(new GenericQuadIterator(self::$quads));
        $triple = DF::quad(DF::namedNode('foo'), DF::namedNode('bar'), DF::literal('baz', 'de'));

        // by Quad
        foreach (self::$quads as $i) {
            $this->assertTrue(isset($d[$i]));
            $this->assertTrue($i->equals($d[$i]));
        }
        $this->assertFalse(isset($d[$triple]));
        try {
            $x = $d[$triple];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }

        // by QuadTemplate
        $tmpl = DF::quadTemplate(DF::namedNode('bar'));
        $this->assertTrue(self::$quads[2]->equals($d[$tmpl]));
        try {
            $tmpl = DF::quadTemplate(null, DF::namedNode('bar'));
            $x    = $d[$tmpl];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }

        // by callback
        $fn = function(iQuad $q, iDataset $d) {
            return $q->getSubject()->getValue() === 'bar';
        };
        $this->assertTrue(self::$quads[2]->equals($d[$fn]));
        try {
            $fn = function(iQuad $q, iDataset $d) {
                return $q->getPredicate()->getValue() === 'bar';
            };
            $x = $d[$fn];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }
    }

    public function testOffsetGetNoIndex(): void {
        $d = new Dataset(false);
        $d->add(new GenericQuadIterator(self::$quads));
        $q = $d[DF::quadTemplate(self::$quads[1]->getSubject())];
        $this->assertTrue(self::$quads[1]->equals($q));
    }

    public function testOffsetSet(): void {
        $d   = new Dataset();
        $d[] = self::$quads[0];
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[0], $d);

        $d[] = self::$quads[1];
        $d[] = self::$quads[2];
        $this->assertCount(3, $d);
        $this->assertContains(self::$quads[1], $d);
        $this->assertContains(self::$quads[2], $d);

        // by Quad
        // 0 + foo bar "baz"
        // 1 + baz foo bar
        // 2 + bar baz foo
        // 3 - foo bar "baz"@en graph
        $d[self::$quads[1]] = self::$quads[3];
        $this->assertCount(3, $d);
        $d[self::$quads[3]] = self::$quads[2];
        $this->assertCount(2, $d);
        $this->assertContains(self::$quads[0], $d);
        $this->assertContains(self::$quads[2], $d);
        $this->assertNotContains(self::$quads[1], $d);
        $this->assertNotContains(self::$quads[3], $d);
        try {
            $d[self::$quads[3]] = self::$quads[1];
            $this->assertTrue(false);
        } catch (OutOfBoundsException $ex) {
            
        }

        // by QuadTemplate
        // 0 + foo bar "baz"
        // 1 - baz foo bar
        // 2 + bar baz foo
        // 3 - foo bar "baz"@en graph
        $tmpl     = DF::quadTemplate(DF::namedNode('bar'), DF::namedNode('baz'));
        $d[$tmpl] = self::$quads[3];
        $this->assertCount(2, $d);
        $this->assertContains(self::$quads[3], $d);
        $this->assertNotContains(self::$quads[2], $d);
        try {
            // two quads match
            $d[DF::quadTemplate(DF::namedNode('foo'))] = self::$quads[0];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }
        try {
            // no quad matches
            $d[DF::quadTemplate(DF::namedNode('bar'), DF::namedNode('foo'))] = self::$quads[0];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }
        try {
            // no quad matches
            $d[DF::quadTemplate(DF::namedNode('aaa'))] = self::$quads[0];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }

        // by callback
        // 0 + foo bar "baz"
        // 1 - baz foo bar
        // 2 - bar baz foo
        // 3 + foo bar "baz"@en graph
        $fn = function(iQuad $q, iDataset $d) {
            return $q->getGraphIri()->getValue() === 'graph';
        };
        $d[$fn] = self::$quads[2];
        $this->assertCount(2, $d);
        $this->assertContains(self::$quads[2], $d);
        $this->assertNotContains(self::$quads[3], $d);
        $d[]    = self::$quads[3];
        try {
            // many matches
            $fn = function(iQuad $q, iDataset $d) {
                return $q->getSubject()->getValue() === 'foo';
            };
            $d[$fn] = self::$quads[1];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }
        try {
            // no match
            $fn = function(iQuad $q, iDataset $d) {
                return $q->getSubject()->getValue() === 'aaa';
            };
            $d[$fn] = self::$quads[1];
            $this->assertTrue(false);
        } catch (OutOfBoundsException) {
            
        }
    }

    public function testOffsetUnSet(): void {
        $d  = new Dataset();
        $d->add(new GenericQuadIterator(self::$quads));
        $this->assertCount(4, $d);
        // by Quad
        unset($d[self::$quads[0]]);
        $this->assertCount(3, $d);
        $this->assertNotContains(self::$quads[0], $d);
        // by QuadTemplate
        unset($d[DF::quadTemplate(self::$quads[1]->getSubject())]);
        $this->assertCount(2, $d);
        $this->assertNotContains(self::$quads[1], $d);
        // by callable
        $fn = function(iQuad $x) {
            return $x->getSubject()->getValue() === 'bar';
        };
        unset($d[$fn]);
        $this->assertCount(1, $d);
        $this->assertNotContains(self::$quads[2], $d);
        // unset non-existent
        unset($d[self::$quads[0]]);
        $this->assertCount(1, $d);
        $this->assertContains(self::$quads[3], $d);
    }

    public function testToString(): void {
        $d   = new Dataset();
        $d->add(self::$quads[0]);
        $d->add(self::$quads[1]);
        $ref = self::$quads[0] . "\n" . self::$quads[1] . "\n";
        $this->assertEquals($ref, (string) $d);
    }

    public function testEquals(): void {
        $d1 = new Dataset();
        $d2 = new Dataset();

        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[1];
        $this->assertTrue($d1->equals($d2));

        $d2[] = self::$quads[2];
        $this->assertFalse($d1->equals($d2));

        unset($d2[self::$quads[2]]);
        $this->assertTrue($d1->equals($d2));

        unset($d2[self::$quads[1]]);
        $this->assertFalse($d1->equals($d2));

        // blank nodes don't count
        $d2[] = self::$quads[1];
        $d1[] = DF::quad(DF::blankNode(), DF::namedNode('foo'), DF::literal('bar'));
        $this->assertTrue($d1->equals($d2));
        $d2[] = DF::quad(DF::blankNode(), DF::namedNode('bar'), DF::literal('baz'));
        $this->assertTrue($d1->equals($d2));
    }

    public function testCopy(): void {
        $d1 = new Dataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // simple
        $d2 = $d1->copy();
        $this->assertTrue($d1->equals($d2));
        unset($d2[self::$quads[0]]);
        $this->assertCount(4, $d1);
        $this->assertCount(3, $d2);
        $this->assertFalse($d1->equals($d2));

        // Quad
        $d2 = $d1->copy(self::$quads[0]);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(1, $d2);
        $this->assertContains(self::$quads[0], $d2);

        // QuadTemplate
        $d2   = $d1->copy(DF::quadTemplate(DF::namedNode('foo')));
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(2, $d2);
        $d2[] = self::$quads[1];
        $d2[] = self::$quads[2];
        $this->assertTrue($d1->equals($d2));

        // QuadIterator
        $d2 = $d1->copy($d1);
        $this->assertTrue($d1->equals($d2));

        // callable
        $fn = function(iQuad $x): bool {
            return false;
        };
        $d2 = $d1->copy($fn);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);
    }

    public function testCopyExcept(): void {
        $d1 = new Dataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // simple
        $d2 = $d1->copyExcept();
        $this->assertTrue($d1->equals($d2));

        // Quad
        $d2   = $d1->copyExcept(self::$quads[0]);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(3, $d2);
        $d2[] = self::$quads[0];
        $this->assertTrue($d1->equals($d2));

        // QuadTemplate
        $d2   = $d1->copyExcept(DF::quadTemplate(DF::namedNode('foo')));
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(2, $d2);
        $d2[] = self::$quads[0];
        $d2[] = self::$quads[3];
        $this->assertTrue($d1->equals($d2));

        // QuadIterator
        $d2 = $d1->copyExcept($d1);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);

        // callable
        $fn = function(iQuad $x): bool {
            return true;
        };
        $d2 = $d1->copyExcept($fn);
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(0, $d2);

        $d1 = new Dataset(false);
        $d1->add(new GenericQuadIterator(self::$quads));
        $d2 = $d1->copyExcept(DF::quadTemplate(self::$quads[0]->getSubject()));
        $this->assertFalse($d1->equals($d2));
        $this->assertCount(2, $d2);
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);
    }

    public function testDelete(): void {
        $d1 = new Dataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2 = $d1->copy();
        $d2->delete(self::$quads[0]->withSubject(DF::blankNode()));
        $this->assertCount(4, $d2);
        $this->assertTrue($d2->equals($d1));

        $d2->delete(self::$quads[0]);
        $this->assertCount(3, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);

        // QuadTemplate
        $d2 = $d1->copy();
        $d2->delete(DF::quadTemplate(DF::namedNode('foo')));
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);

        // QuadIterator
        $d2 = $d1->copy();
        $d2->delete($d1);
        $this->assertCount(0, $d2);
        $this->assertFalse($d2->equals($d1));

        // callable
        $fn = function(iQuad $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $d2 = $d1->copy();
        $d2->delete($fn);
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertNotContains(self::$quads[0], $d2);
        $this->assertNotContains(self::$quads[3], $d2);
    }

    public function testDeleteExcept(): void {
        $d1 = new Dataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $d2 = $d1->copy();
        $d2->deleteExcept(self::$quads[0]->withSubject(DF::blankNode()));
        $this->assertCount(0, $d2);
        $this->assertFalse($d2->equals($d1));

        $d2 = $d1->copy();
        $d2->deleteExcept(self::$quads[0]);
        $this->assertCount(1, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertContains(self::$quads[0], $d2);

        // QuadTemplate
        $d2 = $d1->copy();
        $d2->deleteExcept(DF::quadTemplate(DF::namedNode('foo')));
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertContains(self::$quads[0], $d2);
        $this->assertContains(self::$quads[3], $d2);

        // QuadIterator
        $d2 = $d1->copy();
        $d2->deleteExcept($d1);
        $this->assertCount(4, $d2);
        $this->assertTrue($d2->equals($d1));

        // callable
        $fn = function(iQuad $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $d2 = $d1->copy();
        $d2->deleteExcept($fn);
        $this->assertCount(2, $d2);
        $this->assertFalse($d2->equals($d1));
        $this->assertContains(self::$quads[0], $d2);
        $this->assertContains(self::$quads[3], $d2);
    }

    public function testUnion(): void {
        $d1   = new Dataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d2   = new Dataset();
        $d2[] = self::$quads[1];
        $d2[] = self::$quads[2];

        $d11 = $d1->copy();
        $d22 = $d2->copy();
        $d3  = $d1->union($d2);
        $this->assertCount(2, $d1);
        $this->assertCount(2, $d2);
        $this->assertCount(3, $d3);
        $this->assertTrue($d11->equals($d1));
        $this->assertTrue($d22->equals($d2));
        $this->assertFalse($d3->equals($d1));
        $this->assertFalse($d3->equals($d2));
    }

    public function testXor(): void {
        $d1   = new Dataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[1];
        $d2   = new Dataset();
        $d2[] = self::$quads[1];
        $d2[] = self::$quads[2];

        $d11 = $d1->copy();
        $d22 = $d2->copy();
        $d3  = $d1->xor($d2);
        $this->assertCount(2, $d1);
        $this->assertCount(2, $d2);
        $this->assertCount(2, $d3);
        $this->assertFalse($d3->equals($d1));
        $this->assertFalse($d3->equals($d2));
        $this->assertContains(self::$quads[0], $d3);
        $this->assertContains(self::$quads[2], $d3);
        $this->assertNotContains(self::$quads[1], $d3);
    }

    public function testForEach(): void {
        $d   = new Dataset();
        $d[] = DF::quad(DF::namedNode('foo'), DF::namedNode('baz'), DF::literal(1));
        $d[] = DF::quad(DF::namedNode('bar'), DF::namedNode('baz'), DF::literal(5));
        $d->forEach(function (iQuad $x): iQuad {
            $obj = $x->getObject();
            return $obj instanceof iLiteral ? $x->withObject($obj->withValue((float) (string) $obj->getValue() * 2)) : $x;
        });
        $this->assertEquals(2, (int) (string) $d[DF::quadTemplate(DF::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(10, (int) (string) $d[DF::quadTemplate(DF::namedNode('bar'))]->getObject()->getValue());
    }

    public function testMap(): void {
        $d1   = new Dataset();
        $d1[] = DF::quad(DF::namedNode('foo'), DF::namedNode('baz'), DF::literal(1));
        $d1[] = DF::quad(DF::namedNode('bar'), DF::namedNode('baz'), DF::literal(5));
        $d2   = $d1->map(function (iQuad $x) {
            $obj = $x->getObject();
            return $obj instanceof iLiteral ? $x->withObject($obj->withValue((float) (string) $obj->getValue() * 2)) : $x;
        });
        $this->assertCount(2, $d1);
        $this->assertEquals(1, (int) (string) $d1[DF::quadTemplate(DF::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(5, (int) (string) $d1[DF::quadTemplate(DF::namedNode('bar'))]->getObject()->getValue());
        $this->assertCount(2, $d2);
        $this->assertEquals(2, (int) (string) $d2[DF::quadTemplate(DF::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(10, (int) (string) $d2[DF::quadTemplate(DF::namedNode('bar'))]->getObject()->getValue());
    }

    public function testReduce(): void {
        $d1   = new Dataset();
        $d1[] = DF::quad(DF::namedNode('foo'), DF::namedNode('baz'), DF::literal(1));
        $d1[] = DF::quad(DF::namedNode('bar'), DF::namedNode('baz'), DF::literal(5));
        $sum  = $d1->reduce(function (float $sum, iQuad $x) {
            return $sum + (float) (string) $x->getObject()->getValue();
        }, 0);
        $this->assertEquals(6, $sum);
        $this->assertCount(2, $d1);
        $this->assertEquals(1, (int) (string) $d1[DF::quadTemplate(DF::namedNode('foo'))]->getObject()->getValue());
        $this->assertEquals(5, (int) (string) $d1[DF::quadTemplate(DF::namedNode('bar'))]->getObject()->getValue());
    }

    public function testAnyNoneEvery(): void {
        $d1 = new Dataset();
        $d1->add(new GenericQuadIterator(self::$quads));

        // Quad
        $this->assertTrue($d1->any(self::$quads[0]));
        $this->assertFalse($d1->none(self::$quads[0]));
        $this->assertFalse($d1->any(self::$quads[0]->withSubject(DF::namedNode('aaa'))));
        $this->assertTrue($d1->none(self::$quads[0]->withSubject(DF::namedNode('aaa'))));

        // QuadTemplate
        $this->assertTrue($d1->any(DF::quadTemplate(DF::namedNode('foo'))));
        $this->assertFalse($d1->none(DF::quadTemplate(DF::namedNode('foo'))));
        $this->assertFalse($d1->any(DF::quadTemplate(DF::namedNode('aaa'))));
        $this->assertTrue($d1->none(DF::quadTemplate(DF::namedNode('aaa'))));

        // QuadIterator
        $d2   = new Dataset();
        $d2[] = self::$quads[0];
        $this->assertTrue($d1->any($d2));
        $this->assertFalse($d1->none($d2));

        $d2   = new Dataset();
        $d2[] = self::$quads[0]->withSubject(DF::namedNode('aaa'));
        $this->assertFalse($d1->any($d2));
        $this->assertTrue($d1->none($d2));

        // callable
        $fn = function(iQuad $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $this->assertTrue($d1->any($fn));
        $this->assertFalse($d1->none($fn));

        $fn = function(iQuad $x): bool {
            return $x->getSubject()->getValue() === 'aaa';
        };
        $this->assertFalse($d1->any($fn));
        $this->assertTrue($d1->none($fn));
    }

    public function testEvery(): void {
        // Quad
        $d1   = new Dataset();
        $d1[] = self::$quads[0];
        $this->assertTrue($d1->every(self::$quads[0]));
        $d1[] = self::$quads[1];
        $this->assertFalse($d1->every(self::$quads[0]));
        $this->assertFalse($d1->every(self::$quads[0]->withSubject(DF::namedNode('aaa'))));

        // QuadTemplate
        $d1   = new Dataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[3];
        $this->assertTrue($d1->every(DF::quadTemplate(DF::namedNode('foo'))));
        $this->assertFalse($d1->none(DF::quadTemplate(null, null, DF::literal('baz', 'en'))));

        // callable
        $d1   = new Dataset();
        $d1[] = self::$quads[0];
        $d1[] = self::$quads[3];
        $fn   = function(iQuad $x): bool {
            return $x->getSubject()->getValue() === 'foo';
        };
        $this->assertTrue($d1->every($fn));
        $fn = function(iQuad $x): bool {
            $obj = $x->getObject();
            return $obj instanceof iLiteral ? $obj->getLang() === 'en' : false;
        };
        $this->assertFalse($d1->every($fn));
    }
    
}

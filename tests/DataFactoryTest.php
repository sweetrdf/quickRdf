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

use quickRdf\DataFactory as DF;

/**
 * Description of LoggerTest
 *
 * @author zozlak
 */
class DataFactoryTest extends \rdfInterface\tests\DataFactoryInterfaceTest {

    use TestTrait;

    public function testImportTerms(): void {
        $l  = self::$df::literal('foo');
        $fl = self::$fdf::literal('foo');
        $this->assertSame($l, DF::importTerm($fl));

        $nn  = self::$df::namedNode('foo');
        $fnn = self::$fdf::namedNode('foo');
        $this->assertSame($nn, DF::importTerm($fnn));

        $bn  = self::$df::blankNode('foo');
        $fbn = self::$fdf::blankNode('foo');
        $this->assertSame($bn, DF::importTerm($fbn));

        $dg  = self::$df::defaultGraph();
        $fdg = self::$fdf::defaultGraph();
        $this->assertSame($dg, DF::importTerm($fdg));

        $fq = self::$fdf::quad($fbn, $fnn, $fl, $fdg);
        $qq = DF::importQuad($fq); // importQuad() instead of importTerm() to make phpstan happy
        $q  = self::$df::quad($bn, $nn, $l, $dg);
        $this->assertSame($q, $qq);
        $this->assertSame($l, $qq->getObject());

        try {
            DF::importTerm(new TestUnsupportedTerm());
            $this->assertTrue(false);
        } catch (RdfException) {
            
        }
    }

    public function testHashException(): void {
        $nn = self::$df::namedNode('foo');
        $dg = self::$df::defaultGraph();
        $qt = new TestUnsupportedTerm();
        try {
            self::$df::quad($qt, $nn, $qt);
            $this->assertTrue(false);
        } catch (RdfException) {
            $this->assertTrue(true);
        }
    }

    public function testRdfStar(): void {
        $nn    = self::$df::namedNode('foo');
        $q     = self::$df::quad($nn, $nn, $nn);
        $qstar = self::$df::quad($q, $nn, $q);
        $this->assertIsString((string) $qstar);
    }

    public function testCountReferences(): void {
        $base = DF::getCacheCounts();
        $iri  = random_bytes(100);

        $n1     = DF::namedNode($iri);
        $n2     = DF::namedNode($iri);
        $type   = $n1::class;
        $this->assertTrue($n1 === $n2);
        $counts = DF::getCacheCounts();
        $this->assertEquals(1, $counts[$type]->total - $base[$type]->total);
        $this->assertEquals(1, $counts[$type]->valid - $base[$type]->valid);

        unset($n1);
        $counts = DF::getCacheCounts();
        $this->assertEquals(1, $counts[$type]->total - $base[$type]->total);
        $this->assertEquals(1, $counts[$type]->valid - $base[$type]->valid);

        unset($n2);
        $counts = DF::getCacheCounts();
        $this->assertEquals(1, $counts[$type]->total - $base[$type]->total);
        $this->assertEquals(0, $counts[$type]->valid - $base[$type]->valid);
    }
}

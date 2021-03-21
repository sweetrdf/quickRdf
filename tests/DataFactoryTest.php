<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace quickRdf;

use quickRdf\DataFactory as DF;

/**
 * Description of LoggerTest
 *
 * @author zozlak
 */
class DataFactoryTest extends \rdfInterface\tests\DataFactoryTest {

    use TestTrait;

    public function testCreateVariable(): void {
        $this->expectException(RdfException::class);
        parent::testCreateVariable();
    }

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
            DF::importTerm(self::$fdf::quadTemplate($fnn));
            $this->assertTrue(false);
        } catch (RdfException) {
            
        }
    }

    public function testHashException(): void {
        $nn = self::$df::namedNode('foo');
        $dg = self::$df::defaultGraph();
        $qt = self::$df::quadTemplate($dg);
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

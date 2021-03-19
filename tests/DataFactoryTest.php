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

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
class DatasetTest extends \rdfInterface\tests\DatasetTest {

    use TestTrait;
    
    public function testOffsetGetNoIndex(): void {
        $d = new Dataset(false);
        $d->add(new GenericQuadIterator(self::$quads));
        $q = $d[DF::quadTemplate(self::$quads[1]->getSubject())];
        $this->assertTrue(self::$quads[1]->equals($q));
    }
    
}

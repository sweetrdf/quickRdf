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

    public function testForeignTerms(): void {
        $nn = self::$df::namedNode('foo');
        $q  = self::$df::quad($nn, $nn, $nn);

        $fnn = self::$fdf::namedNode('foo');
        $fq  = self::$fdf::quad($fnn, $fnn, $fnn);
        $fqt = self::$fdf::quadTemplate($fnn);
        $fqi = new GenericQuadIterator($fq);
        $fd  = self::getForeignDataset();
        $fd->add($fq);

        $d  = self::getDataset();
        $d->add($q);
        $this->assertTrue($d->equals($fd));
        $this->assertTrue(isset($d[$fq]));

        $d->add($fq);
        $this->assertEquals(1, count($d));
        $this->assertTrue(isset($d[$fq]));
        $d->add($fqi);
        $this->assertEquals(1, count($d));
        $this->assertTrue(isset($d[$fq]));
        $d[$fqt] = $fq;
        $this->assertEquals(1, count($d));
        $this->assertTrue(isset($d[$fq]));


        //             | Quad | Template | Iterator | outcome
        //isset        |    used to test outcome
        //equals       |            +               | true
        //add          |   +  |    NA    |     +    | isset,count=1
        //copy         |      |          |          | isset
        //deleteExcept |      |          |          | isset
        //copyExcept   |      |          |          | isset
        //delete       |      |          |          | count=0
        //union        |      |          |          | count=1
        //xor          |      |          |          | count=0
        //any          |      |          |          | true 
        //every        |      |          |          | true
        //none         |      |          |          | false
        //[]=          |      |          |    NA    | isset
        //[x]=         |      |          |    NA    | isset




//        $d1->add($q);
//        $d1->add($fq);
//        $this->assertEquals(1, count($d1));
//        $this->assertTrue(isset($d1[$fq]));
//        $this->assertTrue($d1->every($fq));
//        $this->assertTrue($d1->any($fq));
//
//        $d2 = $d1->copy($fq);
//        $this->assertEquals(1, count($d2));
//        $d2 = $d1->copy(new GenericQuadIterator($fq));
//        $this->assertEquals(1, count($d2));
//
//        $d2 = $d1->copyExcept($fq);
//        $this->assertEquals(0, count($d2));
//        $d2 = $d1->copyExcept(new GenericQuadIterator($fq));
//        $this->assertEquals(0, count($d2));
//
//        $d2 = $d1->copy();
//        $d2->delete(self::$fdf::quadTemplate($fnn));
//        $this->assertEquals(0, count($d2));
    }
}

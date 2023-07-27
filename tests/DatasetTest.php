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

use rdfHelpers\GenericQuadIterator;
use rdfInterface\LiteralInterface as iLiteral;
use termTemplates\LiteralTemplate;
use termTemplates\QuadTemplate;

/**
 * Description of LoggerTest
 *
 * @author zozlak
 */
class DatasetTest extends \rdfInterface\tests\DatasetInterfaceTest {

    use TestTrait;

    public function testOffsetGetNoIndex(): void {
        $d = new Dataset(false);
        $d->add(new GenericQuadIterator(self::$quads));
        $q = $d[static::getQuadTemplate(self::$quads[1]->getSubject())];
        $this->assertTrue(self::$quads[1]->equals($q));
    }

    /**
     * Corner cases for findByIndices()
     * @return void
     */
    public function testFindByIndices(): void {
        foreach ([0, 1] as $indexed) {
            // single non-indexable term
            $qt = new QuadTemplate(null, null, new LiteralTemplate(null, LiteralTemplate::EQUALS, ''));
            //0 <foo> <bar> "baz"
            //1 <baz> <foo> <bar>
            //2 <bar> <baz> <foo>
            //3 <foo> <bar> "baz"@en <graph>
            $d  = new Dataset((bool) $indexed);
            $d->add(new GenericQuadIterator(self::$quads));

            $dd = $d->copy($qt);
            $this->assertCount(1, $dd, "Indexed: $indexed");
            foreach ($dd as $i) {
                $obj = $i->getObject();
                $this->assertTrue($obj instanceof iLiteral && $obj->getLang() === 'en', "Indexed: $indexed");
            }

            $dd = $d->copyExcept($qt);
            $this->assertCount(3, $dd, "Indexed: $indexed");
            foreach ($dd as $i) {
                $obj = $i->getObject();
                $this->assertTrue(!($obj instanceof iLiteral) || $obj->getLang() === null, "Indexed: $indexed");
            }

            // mix of indexable and non-indexable terms
            $qt = new QuadTemplate(static::$df::namedNode('foo'), null, new LiteralTemplate(null, LiteralTemplate::EQUALS, ''));
            $d->add(self::$quads[3]->withSubject(self::$quads[1]->getSubject()));
            //0 <foo> <bar> "baz"
            //1 <baz> <foo> <bar>
            //2 <bar> <baz> <foo>
            //3 <foo> <bar> "baz"@en <graph>
            //4 <baz> <bar> "baz"@en <graph>

            $dd = $d->copy($qt);
            $this->assertCount(1, $dd);
            foreach ($dd as $i) {
                $obj = $i->getObject();
                $this->assertEquals('foo', $i->getSubject()->getValue(), "Indexed: $indexed");
                $this->assertTrue($obj instanceof iLiteral && $obj->getLang() === 'en', "Indexed: $indexed");
            }

            $dd = $d->copyExcept($qt);
            $this->assertCount(4, $dd, "Indexed: $indexed");
            foreach ($dd as $i) {
                $obj = $i->getObject();
                $this->assertTrue(!($obj instanceof iLiteral) || $obj->getLang() === null || $i->getSubject()->getValue() !== 'foo', "Indexed: $indexed");
            }
        }
    }
}

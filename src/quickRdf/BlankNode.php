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

use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\Term as iTerm;
use quickRdf\DataFactory as DF;

/**
 * Description of BlankNode
 *
 * @author zozlak
 */
class BlankNode implements \rdfInterface\BlankNode, SingletonTerm {

    private static int $n = 0;

    /**
     * Resets the counter used to assign blank node ids.
     * Useful when predictable blank node ids are required, e.g. in tests context
     * @return void
     */
    public static function resetCounter(): void {
        self::$n = 0;
    }

    /**
     *
     * @var string
     */
    private $id;

    public function __construct(?string $id = null) {
        (!DF::$enforceConstructor) || DF::checkCall();
        if (empty($id)) {
            $id = "_:genid" . self::$n;
            self::$n++;
        }
        if (!str_starts_with($id, '_:')) {
            $id = '_:' . $id;
        }
        $this->id = $id;
    }

    public function __toString(): string {
        return $this->id;
    }

    public function equals(iTerm $term): bool {
        if ($term instanceof SingletonTerm) {
            return $this === $term;
        } else {
            return $term instanceof iBlankNode && $this->getValue() === $term->getValue();
        }
    }

    public function getValue(): string {
        return $this->id;
    }
}

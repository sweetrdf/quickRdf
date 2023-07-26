<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace quickRdf;

/**
 * Dummy class used in tests to trigger unsupported term-related exceptions.
 *
 * @author zozlak
 */
class TestUnsupportedTerm implements \rdfInterface\TermInterface {

    public function __toString(): string {
        return '';
    }

    public function equals(\rdfInterface\TermCompareInterface $term): bool {
        return false;
    }

    public function getValue(): mixed {
        return '';
    }
}

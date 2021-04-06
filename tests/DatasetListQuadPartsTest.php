<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace quickRdf;

/**
 * Description of DatasetListQuadPartsTest
 *
 * @author zozlak
 */
class DatasetListQuadPartsTest extends \rdfInterface\tests\DatasetListQuadPartsTest {

    use TestTrait;
    
    public static function getDataset(): \rdfInterface\DatasetListQuadParts {
        return new Dataset();
    }
}

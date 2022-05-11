<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlIdosellListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlIdosellListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlIdosellListProducts $listProducts */
        $listProducts =  app(XmlIdosellListProducts::class, [
            'urlFull' => env('URL_1'),
            'urlLight' =>env('URL_2'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

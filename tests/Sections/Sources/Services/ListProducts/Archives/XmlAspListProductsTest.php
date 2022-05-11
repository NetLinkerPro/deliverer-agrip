<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlAspListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlAspListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlAspListProducts $listProducts */
        $listProducts =  app(XmlAspListProducts::class, [
            'urlFull' => env('URL_1'),
            'urlLight' =>env('URL_2'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

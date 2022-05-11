<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlWpListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlWpListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlWpListProducts $listProducts */
        $listProducts = app(XmlWpListProducts::class, [
            'url' => env('URL_1'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

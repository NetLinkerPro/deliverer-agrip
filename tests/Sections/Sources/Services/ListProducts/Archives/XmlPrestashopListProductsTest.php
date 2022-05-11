<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlPrestashopListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlPrestashopListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlPrestashopListProducts $listProducts */
        $listProducts = app(XmlPrestashopListProducts::class);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

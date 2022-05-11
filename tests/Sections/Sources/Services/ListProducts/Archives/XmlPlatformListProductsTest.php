<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlPlatformListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlPlatformListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlPlatformListProducts $listProducts */
        $listProducts = app(XmlPlatformListProducts::class, [
            'url' =>env('URL_1'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

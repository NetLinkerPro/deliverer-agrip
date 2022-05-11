<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Prestashop2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlSkyshopListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlSkyshopListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlSkyshopListProducts $listProducts */
        $listProducts = app(XmlSkyshopListProducts::class, [
            'url' => env('URL_1'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

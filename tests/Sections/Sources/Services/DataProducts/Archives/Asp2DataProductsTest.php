<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Asp2DataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Asp2DataProductsTest extends TestCase
{
    public function testDataProducts(){

        /** @var Asp2DataProducts $dataProducts */
        $dataProducts = app(Asp2DataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $product = new ProductSource('W125790694', 'https://www.agrip.com/pl-pl/itemId?itemid=W125790694');
        $product->setPrice(288,61);
        $product->setStock(5);
        $product->setAvailability(1);
        $products = iterator_to_array($dataProducts->get($product));
        $this->assertNotEmpty($products);
    }
}

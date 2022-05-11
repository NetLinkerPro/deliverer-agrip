<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\SymfonyDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SymfonyDataProductsTest extends TestCase
{
    public function testDataProducts(){

        /** @var SymfonyDataProducts $dataProducts */
        $dataProducts = app(SymfonyDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $product = new ProductSource('1519', 'https://agrip.de/-/p/1519');
        $product->setPrice(55.90);
        $product->setStock(5);
        $product->setAvailability(1);
        $product->setTax(23);
        $product->setName('PS Wentylator stojący LTC WT02 40W, 16``, czarny.');
        $product->setCategories([new CategorySource('test', 'Test', 'test')]);
        $products = iterator_to_array($dataProducts->get($product));
        $this->assertNotEmpty($products);
    }
}

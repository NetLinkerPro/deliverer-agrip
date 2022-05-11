<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Prestashop2DataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Prestashop2DataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var Prestashop2DataProducts $dataProducts */
        $dataProducts = app(Prestashop2DataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('27505', 'https://b2b.agrip.pl');
        $product->setAvailability(1);
        $product->setPrice(1299);
        $product->setStock(0);
        $product->setCategories([new CategorySource("1", "2", "3")]);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

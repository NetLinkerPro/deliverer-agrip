<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\CorpsDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class CorpsDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var CorpsDataProducts $dataProducts */
        $dataProducts = app(CorpsDataProducts::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $products = [];
        $product = new ProductSource('EMA105B-S', 'https://b2b.agrip.pl/produkt/EMA105B-S');
        $product->setStock(4);
        $product->setPrice(8.31);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

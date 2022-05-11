<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\EkspertDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class EkspertDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var EkspertDataProducts $dataProducts */
        $dataProducts = app(EkspertDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('82130', 'https://b2b.agrip.pl/offer/show/category/0/id/82130');
        $product->setAvailability(1);
        $product->setProperty('unit', 'szt.');
        $product->setPrice(21.53);
        $product->setStock(30);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

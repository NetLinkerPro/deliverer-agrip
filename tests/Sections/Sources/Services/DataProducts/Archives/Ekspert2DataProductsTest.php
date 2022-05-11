<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Ekspert2DataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Ekspert2DataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var Ekspert2DataProducts $dataProducts */
        $dataProducts = app(Ekspert2DataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('23037', 'https://b2b.ama-europe.pl/offer/show/category/10683/id/23037');
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

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\SupremisB2bDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SupremisB2bDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var SupremisB2bDataProducts $dataProducts */
        $dataProducts = app(SupremisB2bDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        foreach ($dataProducts->get() as $product){
            if ($product->getId() === '48'){
                echo "";
            }
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

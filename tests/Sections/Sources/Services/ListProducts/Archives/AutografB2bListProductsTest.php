<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AutografB2bListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AutografB2bListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var AutografB2bListProducts $listProducts */
        $listProducts = app(AutografB2bListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

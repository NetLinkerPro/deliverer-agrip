<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AutografB2bListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AutografFrontListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AutografFrontListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var AutografFrontListProducts $listProducts */
        $listProducts = app(AutografFrontListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

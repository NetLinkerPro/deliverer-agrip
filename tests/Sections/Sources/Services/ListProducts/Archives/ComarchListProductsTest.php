<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\ComarchListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class ComarchListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var ComarchListProducts $listProducts */
        $listProducts = app(ComarchListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

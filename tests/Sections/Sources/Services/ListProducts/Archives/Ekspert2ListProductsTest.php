<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Ekspert2ListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Ekspert2ListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var Ekspert2ListProducts $listProducts */
        $listProducts = app(Ekspert2ListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

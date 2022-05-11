<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Comarch2ListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Comarch2ListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var Comarch2ListProducts $listProducts */
        $listProducts = app(Comarch2ListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

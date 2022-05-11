<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Comarch2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\DotnetnukeListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class DotnetnukeListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var DotnetnukeListProducts $listProducts */
        $listProducts = app(DotnetnukeListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

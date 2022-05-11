<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\CorpsListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class CorpsListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var CorpsListProducts $listProducts */
        $listProducts = app(CorpsListProducts::class, [
            'loginFtp' => env('LOGIN'),
            'passwordFtp' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

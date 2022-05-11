<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\WmcListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class WmcListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var WmcListProducts $listProducts */
        $listProducts = app(WmcListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

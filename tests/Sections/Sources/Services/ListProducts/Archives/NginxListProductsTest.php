<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\NginxListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class NginxListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var NginxListProducts $listProducts */
        $listProducts = app(NginxListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

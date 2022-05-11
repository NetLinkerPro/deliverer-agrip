<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\PhpListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class PhpListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var PhpListProducts $listProducts */
        $listProducts = app(PhpListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

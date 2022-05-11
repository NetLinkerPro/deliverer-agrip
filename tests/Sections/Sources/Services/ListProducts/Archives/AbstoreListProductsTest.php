<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AbstoreListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AbstoreListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var AbstoreListProducts $listProducts */
        $listProducts = app(AbstoreListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

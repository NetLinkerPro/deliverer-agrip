<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Prestashop2ListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Prestashop2ListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var Prestashop2ListProducts $listProducts */
        $listProducts = app(Prestashop2ListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

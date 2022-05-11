<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives\SoapListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SoapListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var SoapListProducts $listProducts */
        $listProducts = app(SoapListProducts::class, [
            'token' => env('TOKEN'),
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

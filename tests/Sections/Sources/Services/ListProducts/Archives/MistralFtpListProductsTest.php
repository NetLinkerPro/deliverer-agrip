<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives\MistralFtpListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MistralFtpListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var MistralFtpListProducts $listProducts */
        $listProducts = app(MistralFtpListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

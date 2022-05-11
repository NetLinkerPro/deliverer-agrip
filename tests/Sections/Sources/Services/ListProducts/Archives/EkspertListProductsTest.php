<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\EkspertListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class EkspertListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var EkspertListProducts $listProducts */
        $listProducts = app(EkspertListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

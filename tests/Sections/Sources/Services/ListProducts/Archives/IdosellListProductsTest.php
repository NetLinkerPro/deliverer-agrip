<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AutografB2bListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\IdosellListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class IdosellListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var IdosellListProducts $listProducts */
        $listProducts = app(IdosellListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SupremisB2bListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SupremisB2bListProductsTest extends TestCase
{
    public function testListCategories(){

        /** @var SupremisB2bListProducts $listProducts */
        $listProducts = app(SupremisB2bListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

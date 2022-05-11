<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\BlListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class BlListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var BlListProducts $listProducts */
        $listProducts = app(BlListProducts::class, [
            'token' =>env('TOKEN'),
        ]);
        $products = $listProducts->get();
        $products = iterator_to_array($products);
        $this->assertNotEmpty($products);
    }
}

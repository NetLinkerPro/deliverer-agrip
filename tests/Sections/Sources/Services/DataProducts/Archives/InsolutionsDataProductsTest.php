<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\InsolutionsDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class InsolutionsDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var InsolutionsDataProducts $dataProducts */
        $dataProducts = app(InsolutionsDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        foreach ($dataProducts->get() as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

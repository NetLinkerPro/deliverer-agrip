<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\InsolutionsDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\EhurtowniaDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class EhurtowniaDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var EhurtowniaDataProducts $dataProducts */
        $dataProducts = app(EhurtowniaDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
            'password2' => env('PASS2'),
        ]);
        $products = [];
        foreach ($dataProducts->get() as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

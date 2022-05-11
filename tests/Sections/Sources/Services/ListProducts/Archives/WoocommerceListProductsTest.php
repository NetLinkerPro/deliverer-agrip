<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\MagentoListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\WoocommerceListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class WoocommerceListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var WoocommerceListProducts $listProducts */
        $listProducts = app(WoocommerceListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $category = new CategorySource('x', 'Urządzenia', 'https://www.agrip.pl/kategoria/urzadzenia/');
        $category->addChild(new CategorySource('y', 'Kosmetyki',
            'https://www.agrip.pl/kategoria/kosmetyki/'));
        $products = iterator_to_array($listProducts->get($category));
        $this->assertNotEmpty($products);
    }
}

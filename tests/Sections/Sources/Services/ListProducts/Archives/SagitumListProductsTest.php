<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SagitumListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SagitumListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var SagitumListProducts $listProducts */
        $listProducts = app(SagitumListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $category = new CategorySource('132', 'Artykuły sportowe', 'https://b2b.agrip.pl/Forms/Articles.aspx?GroupID=132');
        $products = iterator_to_array($listProducts->get($category));
        $this->assertNotEmpty($products);
    }
}

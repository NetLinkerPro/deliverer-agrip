<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\MistralListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MistralListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var MistralListProducts $listProducts */
        $listProducts = app(MistralListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $category = new CategorySource('14771_14771', 'ELEKTRONARZĘDZIA', 'https://www.hurt.aw-narzedzia.com.pl');
        $category->addChild(new CategorySource('14824_14824', 'Bruzdownice', 'https://www.hurt.aw-narzedzia.com.pl'));
        $products = iterator_to_array($listProducts->get($category));
        $this->assertNotEmpty($products);
    }
}

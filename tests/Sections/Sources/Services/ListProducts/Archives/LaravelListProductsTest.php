<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\LaravelListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class LaravelListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var LaravelListProducts $listProducts */
        $listProducts = app(LaravelListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
        ]);
        $category = new CategorySource('9', 'Węże i kształtki silikonowe', 'https://b2b.agrip.pl/group/9');
        $category->addChild(new CategorySource('35', 'Przewody proste 1m', 'https://b2b.agrip.pl/group/35'));
        $products = iterator_to_array($listProducts->get($category));
        $this->assertNotEmpty($products);
    }

    public function testGetPriceStockWezeizlacza()
    {
        /** @var LaravelListProducts $listProducts */
        $listProducts = app(LaravelListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
        ]);
        $priceStock = $listProducts->getStockWezeizlacza('ST90 KOLANO100');
        $this->assertNotEmpty($priceStock);
    }
}

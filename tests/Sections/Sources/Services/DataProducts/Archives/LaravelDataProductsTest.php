<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\LaravelDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\WmcDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class LaravelDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var LaravelDataProducts $dataProducts */
        $dataProducts = app(LaravelDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
        ]);
        $products = [];
        $product = new ProductSource('2478', 'https://b2b.agrip.pl/product/2478');
        $category = new CategorySource('x', 'x', 'https://b2b.agrip.pl/');
        $category->addChild(new CategorySource('9', 'Węże i kształtki silikonowe', 'https://b2b.agrip.pl/group/9'));
       $product->addCategory($category);
        $product->setAvailability(1);
        $product->setProperty('unit', 'szt.');
        $product->setPrice(21.53);
        $product->setStock(30);
        $product->setName('Dzwonek elektromechaniczny dwutonowy BREVIS MINI AC 230V biały');
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

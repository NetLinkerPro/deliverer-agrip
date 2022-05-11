<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\WmcDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class WmcDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var WmcDataProducts $dataProducts */
        $dataProducts = app(WmcDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('884', 'https://b2b.agrip.pl/wmc/product/product/884/show');
        $category = new CategorySource('x', 'x', 'https://b2b.agrip.pl/');
        $category->addChild(new CategorySource('xxx_xx__807', 'Dzwonki przewodowe', 'https://b2b.agrip.pl/wmc/order/order/list-product?category=807'));
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

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Comarch2DataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Comarch2DataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var Comarch2DataProducts $dataProducts */
        $dataProducts = app(Comarch2DataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
        ]);
        $category = new CategorySource('37', 'BE ACTIVE - AKTYWNE DZIECKO', 'https://b2b.agrip.pl/items/37?parent=null');
        $category->addChild(new CategorySource('48', 'GRYZAKI', 'https://b2b.agrip.pl/items/48?parent=null'));
        $products = [];
        $product = new ProductSource('80012', 'http://www.b2b.agrip.info/itemdetails/80012?group=null');
        $product->addCategory($category);
        $product->setStock(15);
        $product->setPrice(15.00);
        $product->setAvailability(1);
        $product->setTax(23);
        $product->addCategory($category);
        $product->setProperty('unit', 'szt.');
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

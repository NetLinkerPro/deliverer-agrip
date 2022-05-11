<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\AspDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\SoapDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SoapListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AspDataProductsTest extends TestCase
{
    public function testDataProducts(){

        /** @var AspDataProducts $dataProducts */
        $dataProducts = app(AspDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $product = new ProductSource('12112', 'https://b2b.agrip.net.pl/Towar/12112');
        $product->setPrice(20);
        $product->setStock(2);
        $product->setAvailability(1);
        $product->setTax(23);
        $product->setCategories([new CategorySource('test', 'Test', 'test')]);
        $products = iterator_to_array($dataProducts->get($product));
        $this->assertNotEmpty($products);
    }
}

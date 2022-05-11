<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\PhpDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class PhpDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var PhpDataProducts $dataProducts */
        $dataProducts = app(PhpDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('10135', 'https://www.agrip.pl/10135-alkohol-izopropylowy-ag-termopasty-kontakt-ipa-100ml-agt-002-oliwiarka/');
        $product->setStock(15);
        $product->setPrice(15.00);
        $product->setTax(15.00);
        $product->setAvailability(1);
        $product->setProperty('unit', 'szt.');
        $product->setProperty('minimum_quantity', '1');
        $product->setProperty('long_name', 'Long name');
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

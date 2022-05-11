<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\ComarchDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\CorpsDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class ComarchDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var ComarchDataProducts $dataProducts */
        $dataProducts = app(ComarchDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('755', 'https://agrip.pl/czujnik-parkowania-bialy-22,3,76,755');
        $product->setName('Ładowarka przód-tył z przedłużaczem 4 USB');
        $product->setStock(15);
        $product->setPrice(15.00);
        $product->setAvailability(1);
        $product->setProperty('unit', 'szt.');
        $product->setProperty('SKU', '01986');
        $product->setProperty('manufacturer', 'Testowy');
        $product->setProperty('weight', '0,128kg');
        $product->addCategory(new CategorySource('test', 'Testowa', 'https://agrip.pl'));
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\NginxDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class NginxDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var NginxDataProducts $dataProducts */
        $dataProducts = app(NginxDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];

        $product = new ProductSource('41426', 'https://b2b.agrip.pl/karta-produktu.html?twid=41426');
        $product->setStock(22);
        $product->setPrice(21.23);
        $product->setTax(23);
        $product->setAvailability(1);
        $product->addCategory(new CategorySource('12', 'Testowa kategoria', 'https://b2b.agrip.pl'));
        $product->setProperty('EAN', '737052912172');
        $product->setProperty('bella_image','https://4bella.pl/5276/-.jpg');
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

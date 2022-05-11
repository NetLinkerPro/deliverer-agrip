<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\AbstoreDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\EkspertDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AbstoreDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var AbstoreDataProducts $dataProducts */
        $dataProducts = app(AbstoreDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('4827', 'https://agrip.abstore.pl/fissler-garnek-wysoki-16cm-opc-2,c47,p4827,o1,s1001,pl.html');
        $product->setAvailability(1);
        $product->setPrice(21.53);
        $product->setStock(30);
        $product->setTax(23);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\IdosellDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\NodeDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class NodeDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var NodeDataProducts $dataProducts */
        $dataProducts = app(NodeDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $idProduct = '758923';
        $product = new ProductSource($idProduct, sprintf('https://www.agrip.pl/offer/pl/0/#/product/?pr=%s', $idProduct));
        $product->setProperty('last_category', new CategorySource('31045', 'biżuteria', 'https://www.agrip.pl'));
        $product->setName('Klocki DOTS 41909 Bransoletki z syrenim wdziękiem');
        $product->setAvailability(1);
        $product->setPrice(21.53);
        $product->setStock(30);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

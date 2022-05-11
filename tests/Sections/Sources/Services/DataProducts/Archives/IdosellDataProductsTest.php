<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\IdosellDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class IdosellDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var IdosellDataProducts $dataProducts */
        $dataProducts = app(IdosellDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $idProduct = '88309';
        $product = new ProductSource($idProduct, sprintf('https://agrip.pl/product-pol-%s', $idProduct));
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        foreach ($dataProducts->getLive($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

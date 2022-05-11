<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\MistralDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\WoocommerceDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class WoocommerceDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var WoocommerceDataProducts $dataProducts */
        $dataProducts = app(WoocommerceDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'xmlUrl' =>env('URL_1'),
        ]);
        $products = [];
        $product = new ProductSource('CN02934', 'https://www.agrip.pl/sklep/depilacja/woski/zestaw-depilacja-optima-podgrzewaczwoskx1paski/');
        $product->setAvailability(1);
        $product->setPrice(1299);
        $product->setStock(0);
        $product->setTax(23);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

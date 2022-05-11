<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\MistralDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\SagitumDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SagitumDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var SagitumDataProducts $dataProducts */
        $dataProducts = app(SagitumDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('357', 'https://b2b.agrip.pl/Forms/Article.aspx?ArticleID=357');
        $product->setAvailability(1);
        $product->setPrice(21.53);
        $product->setStock(30);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

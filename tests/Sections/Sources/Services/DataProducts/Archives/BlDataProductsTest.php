<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Asp2DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\BlDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class BlDataProductsTest extends TestCase
{
    public function testDataProducts(){

        /** @var BlDataProducts $dataProducts */
        $dataProducts = app(BlDataProducts::class, [
            'token' => env('TOKEN'),
        ]);
        $product = new ProductSource('1021895329', 'https://panel.baselinker.com/1021895329');
        $product->setPrice(288,61);
        $product->setStock(5);
        $product->setAvailability(1);
        $products = iterator_to_array($dataProducts->get($product));
        $this->assertNotEmpty($products);
    }
}

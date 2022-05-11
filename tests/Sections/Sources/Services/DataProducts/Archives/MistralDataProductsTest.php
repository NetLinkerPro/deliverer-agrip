<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\MistralDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MistralDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        /** @var MistralDataProducts $dataProducts */
        $dataProducts = app(MistralDataProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = [];
        $product = new ProductSource('x', 'https://www.hurt.aw-narzedzia.com.pl/ProduktySzczegoly.aspx?id_artykulu=dYQVj0oVl1sczBcqJTvl4w');
        $product->setProperty('last_category', new CategorySource('31045', 'biżuteria', 'https://www.agrip.pl'));
        $product->setName('Klocki DOTS 41909 Bransoletki z syrenim wdziękiem');
        $product->setAvailability(1);
        $product->setPrice(21.53);
        $product->setStock(30);
        $product->setTax(23);
        $category = new CategorySource('14771_14771', 'ELEKTRONARZĘDZIA', 'https://www.hurt.aw-narzedzia.com.pl');
        $category->addChild(new CategorySource('14824_14824', 'Bruzdownice', 'https://www.hurt.aw-narzedzia.com.pl'));
        $product->setCategories([$category]);
        foreach ($dataProducts->get($product) as $product){
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives\SoapDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives\SoapListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SoapDataProductsTest extends TestCase
{
    public function testDataProducts(){

        /** @var SoapDataProducts $dataProducts */
        $dataProducts = app(SoapDataProducts::class, [
            'token' => env('TOKEN'),
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'urlCsv' =>env('URL_1')
        ]);
        $product = new ProductSource('2244792', 'https://sklep.agrip.pl/pl-pl/produkt/-/2244792/-');
        $product->setPrice(20);
        $product->setStock(0);
        $product->setAvailability(1);
        $product->setTax(23);
        $product->setProperty('Kategoria', 'TFO > Kategorie > GSM > Pokrowce, etui, futerały > Etui z klapką > Otwierane na bok > Dedykowane');
        $products = iterator_to_array($dataProducts->get($product));
        $this->assertNotEmpty($products);
    }
}

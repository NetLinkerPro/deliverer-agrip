<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\MagresnetListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlCeneoListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlCeneoListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlCeneoListProducts $listProducts */
        $listProducts = app(XmlCeneoListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'url' =>env('URL_1'),
        ]);
        $products = $listProducts->get();
        $products = iterator_to_array($products);
        $this->assertNotEmpty($products);
    }
}

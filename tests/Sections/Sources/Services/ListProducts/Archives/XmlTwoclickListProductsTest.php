<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlTwoclickListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlTwoclickListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlTwoclickListProducts $listProducts */
        $listProducts =  app(XmlTwoclickListProducts::class, [
            'url' => env('URL_1'),
            'login' =>env('LOGIN'),
            'password' =>env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

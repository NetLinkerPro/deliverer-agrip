<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\BlListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Ftp2ListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Ftp2ListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var Ftp2ListProducts $listProducts */
        $listProducts = app(Ftp2ListProducts::class, [
            'host' =>env('URL_1'),
            'login' =>env('LOGIN'),
            'password' =>env('PASS'),
        ]);
        $products = $listProducts->get();
        $products = iterator_to_array($products);
        $this->assertNotEmpty($products);
    }
}

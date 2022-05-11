<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\MagentoListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MagentoListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var MagentoListProducts $listProducts */
        $listProducts = app(MagentoListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $category = new CategorySource('x', 'Śruby', 'https://www.simple24.pl/technika-zlaczna/sruby-nakretki-podkladki/sruby.html');
        $category->addChild(new CategorySource('y', 'Śruba z łbem 6-kątnym z gwintem na całości - kl.5.8, ocynk biały',
            'https://www.simple24.pl/nit-zrywalny-alu-stal-z-lbem-wpuszczanym-120.html'));
        $products = iterator_to_array($listProducts->get($category));
        $this->assertNotEmpty($products);
    }
}

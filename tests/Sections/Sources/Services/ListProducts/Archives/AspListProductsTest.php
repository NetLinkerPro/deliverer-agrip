<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AspListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AspListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var AspListProducts $listProducts */
        $listProducts = app(AspListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categoryParent = new CategorySource('28632', 'DOM', 'https://b2b.agrip.net.pl/Towary/DOM/28632');
        $categoryChild = new CategorySource('28635', 'inne', 'https://b2b.agrip.net.pl/Towary/inne/28635');
        $categoryParent->addChild($categoryChild);
        $products = iterator_to_array($listProducts->get($categoryParent));
        $this->assertNotEmpty($products);
    }
}

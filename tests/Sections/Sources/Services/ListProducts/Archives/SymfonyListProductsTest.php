<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AspListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SymfonyListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SymfonyListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var SymfonyListProducts $listProducts */
        $listProducts = app(SymfonyListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categoryParent = new CategorySource('13374', 'AGD', 'https://agrip.de/agd/k/13374');
        $products = iterator_to_array($listProducts->get($categoryParent));
        $this->assertNotEmpty($products);
    }
}

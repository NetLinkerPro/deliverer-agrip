<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AspListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\InsolutionsListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SymfonyListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class InsolutionsListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var InsolutionsListProducts $listProducts */
        $listProducts = app(InsolutionsListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

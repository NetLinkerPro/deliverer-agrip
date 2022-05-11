<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AspListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\EhurtowniaListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\InsolutionsListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SymfonyListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class EhurtowniaListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var EhurtowniaListProducts $listProducts */
        $listProducts = app(EhurtowniaListProducts::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

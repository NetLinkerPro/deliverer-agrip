<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlGimmegratorListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class XmlGimmegratorListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var XmlGimmegratorListProducts $listProducts */
        $listProducts = app(XmlGimmegratorListProducts::class, [
            'url' => env('URL_1'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }
}

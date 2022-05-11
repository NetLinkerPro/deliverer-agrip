<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives\Magento2ListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Magento2ListProductsTest extends TestCase
{
    public function testListProducts()
    {
        /** @var Magento2ListProducts $service */
        $service = app(Magento2ListProducts::class);
        $category = new CategorySource('1584', 'Smycze Flexi', 'https://agrip.de/akcesoria/flexi');
        $products = iterator_to_array($service->get($category));
        $this->assertNotEmpty($products);
    }
}

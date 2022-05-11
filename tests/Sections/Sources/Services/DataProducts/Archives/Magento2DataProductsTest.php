<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives\Magento2DataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Magento2DataProductsTest extends TestCase
{
    public function testDataProducts()
    {
        $product = new ProductSource('test', 'https://agrip.de/catalog/product/view/id/117686');
        /** @var Magento2DataProducts $service */
        $service = app(Magento2DataProducts::class);
        $products = iterator_to_array($service->get($product));
        $this->assertNotEmpty($products);
    }
}

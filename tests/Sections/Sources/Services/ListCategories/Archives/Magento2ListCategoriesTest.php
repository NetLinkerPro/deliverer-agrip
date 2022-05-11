<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives\Magento2ListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Magento2ListCategoriesTest extends TestCase
{
    public function testCategories()
    {
        Artisan::call('cache:clear');
        /** @var Magento2ListCategories $service */
        $service = app(Magento2ListCategories::class);
        $categories = iterator_to_array($service->get());
        $this->assertNotEmpty($categories);
    }
}

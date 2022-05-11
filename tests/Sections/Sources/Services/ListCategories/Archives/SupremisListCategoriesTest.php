<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SupremisListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SymfonyListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SupremisListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var SupremisListCategories $listCategories */
        $listCategories = app(SupremisListCategories::class);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

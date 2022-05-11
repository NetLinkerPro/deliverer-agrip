<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\IdosellListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\NodeListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class NodeListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var NodeListCategories $listCategories */
        $listCategories = app(NodeListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

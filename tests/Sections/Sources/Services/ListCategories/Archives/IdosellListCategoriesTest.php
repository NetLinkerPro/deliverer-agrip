<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\IdosellListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class IdosellListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var IdosellListCategories $listCategories */
        $listCategories = app(IdosellListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

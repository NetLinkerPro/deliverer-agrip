<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\CorpsListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class CorpsListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var CorpsListCategories $listCategories */
        $listCategories = app(CorpsListCategories::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

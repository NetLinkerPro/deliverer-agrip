<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Comarch2ListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Comarch2ListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var Comarch2ListCategories $listCategories */
        $listCategories = app(Comarch2ListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

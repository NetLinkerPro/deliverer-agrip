<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Prestashop2ListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Prestashop2ListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var  $listCategories */
        $listCategories = app(Prestashop2ListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SagitumListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SagitumListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var  $listCategories */
        $listCategories = app(SagitumListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

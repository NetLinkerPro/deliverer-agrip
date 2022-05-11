<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\ComarchListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class ComarchListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var ComarchListCategories $listCategories */
        $listCategories = app(ComarchListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\ComarchListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\MistralListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MistralListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var MistralListCategories $listCategories */
        $listCategories = app(MistralListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

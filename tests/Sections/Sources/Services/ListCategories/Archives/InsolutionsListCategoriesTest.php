<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\InsolutionsListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SymfonyListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class InsolutionsListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var InsolutionsListCategories $listCategories */
        $listCategories = app(InsolutionsListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

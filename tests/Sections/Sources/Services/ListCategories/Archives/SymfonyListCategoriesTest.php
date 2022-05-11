<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SymfonyListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SymfonyListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var SymfonyListCategories $listCategories */
        $listCategories = app(SymfonyListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

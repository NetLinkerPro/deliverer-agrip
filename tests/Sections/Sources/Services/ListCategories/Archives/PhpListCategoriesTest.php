<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\PhpListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class PhpListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var PhpListCategories $listCategories */
        $listCategories = app(PhpListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

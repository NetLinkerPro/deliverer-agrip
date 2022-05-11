<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\AspListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AspListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var AspListCategories $listCategories */
        $listCategories = app(AspListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

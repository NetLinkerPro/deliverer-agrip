<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\WmcListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class WmcListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var WmcListCategories $listCategories */
        $listCategories = app(WmcListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

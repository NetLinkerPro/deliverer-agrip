<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives\SoapListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SoapListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var SoapListCategories $listCategories */
        $listCategories = app(SoapListCategories::class, [
            'token' => env('TOKEN'),
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

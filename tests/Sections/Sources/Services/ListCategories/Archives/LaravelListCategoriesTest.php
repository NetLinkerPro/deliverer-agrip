<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\LaravelListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class LaravelListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var LaravelListCategories $listCategories */
        $listCategories = app(LaravelListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' => env('LOGIN2'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }
}

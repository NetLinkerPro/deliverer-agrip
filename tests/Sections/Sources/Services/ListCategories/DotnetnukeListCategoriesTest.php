<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListCategories;

use GuzzleHttp\Exception\GuzzleException;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Comarch2ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\DotnetnukeListCategories;
use NetLinker\DelivererAgrip\Tests\TestCase;

class DotnetnukeListCategoriesTest extends TestCase
{
    public function testListCategories(){

        /** @var DotnetnukeListCategories $listCategories */
        $listCategories = app(DotnetnukeListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = iterator_to_array($listCategories->get());
        $this->assertNotEmpty($categories);
    }

    /**
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function testCategory1()
    {
        /** @var DotnetnukeListCategories $listCategories */
        $listCategories = app(DotnetnukeListCategories::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $categories = $listCategories->getTreeCategoriesWebsite();
        $this->assertNotEmpty($categories);
    }
}

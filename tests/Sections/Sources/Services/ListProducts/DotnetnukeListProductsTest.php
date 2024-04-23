<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts;

use GuzzleHttp\Exception\GuzzleException;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Categories\Models\Category;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Comarch2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\DotnetnukeListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class DotnetnukeListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var DotnetnukeListProducts $listProducts */
        $listProducts = app(DotnetnukeListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $products = iterator_to_array($listProducts->get());
        $this->assertNotEmpty($products);
    }

    /**
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function testGetProducts(){
        /** @var DotnetnukeListProducts $listProducts */
        $listProducts = app(DotnetnukeListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'configuration' => [],
        ]);

        $category = new CategorySource('2305', 'test', 'https://www.argip.com.pl/Produkty/Zakupy/tabid/85/parentid/77/product/nity-nitonakretki/Default.aspx#');
        $category->setProperty('db', [
            'item_id' => '2305',
            'table_number' =>'1',
        ]);
        $products = iterator_to_array($listProducts->getProducts($category, $category));
        $this->assertNotEmpty($products);
    }
}

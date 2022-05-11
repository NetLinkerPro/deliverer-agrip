<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\ListProducts\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\MagresnetListProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MagresnetListProductsTest extends TestCase
{
    public function testListProducts(){
        /** @var MagresnetListProducts $listProducts */
        $listProducts = app(MagresnetListProducts::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $category = new CategorySource('132', 'Artykuły sportowe', 'https://b2b.agrip.pl/Forms/Articles.aspx?GroupID=132');
        $category->setProperty('name', 'ctl00$ContentPlaceHolder1$lvGrupy$ctrl1$ctl05$ImageButton9');
        $category->setProperty('page', 1);
        $products = $listProducts->get($category);
        foreach ($products as $product){
             $product = $listProducts->getDetails($product);
            echo '';
        }
        $products = iterator_to_array();
        $this->assertNotEmpty($products);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\DataProducts\Archives;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\PrestashopDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class PrestashopDataProductsTest extends TestCase
{
    public function testGetDataProducts()
    {
        Artisan::call('cache:clear');
        /** @var PrestashopDataProducts $dataProducts */
        $dataProducts = app(PrestashopDataProducts::class, [
            'urlApi' => env('URL_1'),
            'apiKey' => env('TOKEN'),
            'debug' =>false,
        ]);
        $products = [];
        foreach ($dataProducts->get() as $product){
            if ($product->getId() === '48'){
                echo "";
            }
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }
}

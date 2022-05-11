<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Targets\Services\Baselinker;

use NetLinker\DelivererAgrip\Sections\Targets\Services\Baselinker\StorageBaselinker;
use NetLinker\DelivererAgrip\Tests\TestCase;
use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;

class StorageBaselinkerTest extends TestCase
{
    public function testGetStockFromStorage()
    {
        /** @var StorageBaselinker $service */
        $service = app(StorageBaselinker::class, ['configurationBaselinker' =>[
            'api_token' => env('API_TOKEN_BASELINKER'),
            'id_category_products' => '467884',
        ]]);
        $shopProduct = ShopProduct::where('deliverer', 'agrip')->firstOrFail();
        $stock = $service->getStock($shopProduct);
        $this->assertTrue($stock === null || $stock >=0);
    }
}

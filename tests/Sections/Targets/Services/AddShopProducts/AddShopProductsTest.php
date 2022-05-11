<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Targets\Services\AddShopProducts;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Sections\Configurations\Models\Configuration;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts\AddProducts;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts\AddShopProducts;
use NetLinker\DelivererAgrip\Tests\Stubs\User;
use NetLinker\DelivererAgrip\Tests\TestCase;
use NetLinker\WideStore\Sections\Shops\Models\Shop;

class AddShopProductsTest extends TestCase
{

    public function testAddProductsToShop(){

        $user = User::first();
        Auth::login($user);

        $shop = Shop::where('owner_uuid', $user->owner_uuid)->firstOrFail();

        $configuration = Configuration::firstOrFail();

        DelivererLogger::listen(function($message){
            Log::debug($message);
        });

        $addShopProducts = new AddShopProducts($shop->uuid, $user->owner_uuid, $configuration->uuid);

        $steps = $addShopProducts->addShopProducts();

        foreach ($steps as $step){

            echo '';
        }
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Targets\Services\UpdateMyPricesStocks;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Sections\Configurations\Models\Configuration;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts\AddProducts;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts\AddShopProducts;
use NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateMyPricesStocks\UpdateMyPricesStocks;
use NetLinker\DelivererAgrip\Tests\Stubs\User;
use NetLinker\DelivererAgrip\Tests\TestCase;
use NetLinker\WideStore\Sections\Shops\Models\Shop;
use NetLinker\WideStore\Sections\ShopStocks\Models\ShopStock;

class UpdateMyPricesStocksTest extends TestCase
{

    public function testUpdateMyPricesStocksToShop(){

        $user = User::first();
        Auth::login($user);

        $shop = Shop::where('owner_uuid', $user->owner_uuid)->firstOrFail();

        $configuration = Configuration::get()->last();

        DelivererLogger::listen(function($message){
             Log::debug($message);
        });

        $updateMyPricesStocks = new UpdateMyPricesStocks($shop->uuid, $user->owner_uuid, $configuration->uuid);

        $steps = $updateMyPricesStocks->updateMyPricesStocks();

        foreach ($steps as $step){

            echo '';
        }
    }
}

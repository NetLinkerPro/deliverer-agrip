<?php


namespace NetLinker\DelivererAgrip\Tests\Production;


use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts\AddShopProducts;
use NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateMyPricesStocks\UpdateMyPricesStocks;
use NetLinker\DelivererAgrip\Tests\TestCase;
use NetLinker\WideStore\Sections\Shops\Models\Shop;

class UpdateMyPricesStocksTest extends TestCase
{
    public function test_update_my_prices_stocks_with_production_database()
    {
        $this->ConnectionDatabases();

        $shop = Shop::findOrFail(6);

        DelivererLogger::listen(function($message){
            Log::debug($message);
        });

        $service = new UpdateMyPricesStocks($shop->uuid, '6bc49b47-89af-44de-a15e-4926cf0af8e9', $shop->configuration_uuid);

       iterator_to_array($service->updateMyPricesStocks(), false);

        echo '';
    }

    private function ConnectionDatabases()
    {
        Config::set('wide-store.connection', 'wide_store');
        Config::set('database.default', 'mysql');
        Config::set('database.connections.wide_store', [
            'driver' => 'mysql',
            'host' => env('DB_WIDE_STORE_HOST'),
            'port' => env('DB_WIDE_STORE_PORT'),
            'database' => env('DB_WIDE_STORE_DATABASE'),
            'username' => env('DB_WIDE_STORE_USERNAME'),
            'password' => env('DB_WIDE_STORE_PASSWORD'),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);
    }
}
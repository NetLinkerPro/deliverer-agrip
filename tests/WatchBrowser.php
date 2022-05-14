<?php

namespace NetLinker\DelivererAgrip\Tests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use NetLinker\DelivererAgrip\Sections\Configurations\Models\Configuration;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\DelivererAgrip\Sections\Jobs\Jobs\AddProductsJob;
use NetLinker\DelivererAgrip\Sections\Jobs\Jobs\AddShopProductsJob;
use NetLinker\DelivererAgrip\Sections\Jobs\Jobs\UpdateProductsJob;
use NetLinker\DelivererAgrip\Sections\Jobs\Jobs\UpdateShopProductsJob;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\DelivererAgrip\Tests\Helpers\SetupHelper;
use NetLinker\DelivererAgrip\Tests\Stubs\Owner;
use NetLinker\DelivererAgrip\Tests\Stubs\User;
use NetLinker\FairQueue\HorizonManager;
use NetLinker\FairQueue\Queues\QueueConfiguration;
use NetLinker\FairQueue\Sections\Horizons\Models\Horizon;
use NetLinker\FairQueue\Sections\Queues\Models\Queue;
use NetLinker\FairQueue\Sections\Supervisors\Models\Supervisor;
use NetLinker\WideStore\Sections\Shops\Models\Shop;

class WatchBrowser extends BrowserTestCase
{

    use SetupHelper;

    /**
     * Refresh the application instance.
     *
     * @return void
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        if (Schema::hasTable('users_test') && User::count()) {
            Auth::login(User::all()->first());
        }
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function watch()
    {
        $owner = factory(Owner::class)->create();
        factory(User::class)->create(['owner_uuid' => $owner->uuid,]);
        Auth::login(User::all()->first());

        $horizon = factory(Horizon::class)->create();
        $supervisor = factory(Supervisor::class)->create();

        $queue1 = factory(Queue::class)->create([
            'horizon_uuid' => $horizon->uuid,
            'supervisor_uuid' => $supervisor->uuid,
            'queue' => 'auto_deliverer_store_add_products',
        ]);

        $queue2 = factory(Queue::class)->create([
            'horizon_uuid' => $horizon->uuid,
            'supervisor_uuid' => $supervisor->uuid,
            'queue' => 'auto_deliverer_store_add_and_update_shop_products',
        ]);

        QueueConfiguration::$queuesResolver = function () use (&$queue1, &$queue2) {
            return [
                'auto_deliverer_store_add_products' => $queue1,
                'auto_deliverer_store_add_and_update_shop_products' => $queue2,
            ];
        };
        HorizonManager::$horizonResolver = function () use (&$horizon) {
            return $horizon;
        };

        $settingRepository = new SettingRepository();
        $setting = $settingRepository->updateOrCreateValue([
            'url_1' => env('URL_1'),
            'url_2' => env('URL_2'),
            'login' => env('LOGIN'),
            'pass' => env('PASS'),
            'login2' => env('LOGIN2'),
            'pass2' => env('PASS2'),
            'token' => env('TOKEN'),
            'debug' => false,
            'from_add_product' => '',
            'add_products_cron' => '',
            'owner_supervisor_uuid' => Auth::user()->owner_uuid,
            'update_exist_images_disk' => false,
            'max_width_images_disk' => 800,
            'limit_products' => '5',
        ]);

        $configuration = Configuration::updateOrCreate([
            'name' => 'Agrip',
            'url_1' => env('URL_1'),
            'url_2' => env('URL_2'),
            'login' => env('LOGIN'),
            'pass' => env('PASS'),
            'login2' => env('LOGIN2'),
            'pass2' => env('PASS2'),
            'token' => env('TOKEN'),
            'debug' => false,
        ], [
            'baselinker' =>[
                'api_token' => env('API_TOKEN_BASELINKER'),
                'id_category_products' => '',
            ]
        ]);

        $formatter = Formatter::updateOrCreate([
            'name' => 'Agrip',
            'identifier_type' => 'default',
            'name_lang' => 'pl', // TODO: Set currency below and in view formatter
            'name_type' => 'default',
            'url_type' =>'default',
            'price_currency' =>'pln',
            'price_type' => 'default',
            'tax_country' => 'pl',
            'stock_type' => 'default',
            'category_lang' =>'pl',
            'category_type' =>'default',
            'image_lang' =>  'pl',
            'image_type' =>'default',
            'attribute_lang' =>  'pl',
            'attribute_type' => 'default',
            'description_lang' =>  'pl',
            'description_type' => 'default',
        ]);

        $shop = app()->make(Shop::class);

        $shop = $shop->updateOrCreate([
            'deliverer' => 'agrip',
            'formatter_uuid' => $formatter->uuid,
        ], [
            'configuration_uuid' =>$configuration->uuid,
            'name' => 'Agrip'
        ]);

//        AddProductsJob::dispatchNow(['setting' => $setting->toArray()]);
        UpdateProductsJob::dispatchNow(['setting' => $setting->toArray()]);

//        AddShopProductsJob::dispatchNow([
//            'shop_uuid' => $shop->uuid,
//            'owner_uuid' => Auth::user()->owner_uuid,
//            'configuration_uuid' =>$configuration->uuid,
//        ]);

        UpdateShopProductsJob::dispatchNow([
            'shop_uuid' => $shop->uuid,
            'owner_uuid' => Auth::user()->owner_uuid,
            'configuration_uuid' =>$configuration->uuid,
        ]);

        $this->browse(function (Browser $browser) {
            TestHelper::maximizeBrowserToScreen($browser);
            $browser->visit('/deliverer-agrip/settings');

            TestHelper::browserWatch($browser, false, ['auto_deliverer_store_add_products', 'auto_deliverer_store_add_and_update_shop_products']);
        });


        $this->assertTrue(true);
    }
}

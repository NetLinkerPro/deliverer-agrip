<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Targets\Services\AddProducts;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts\AddProducts;
use NetLinker\DelivererAgrip\Tests\Helpers\Authorization;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AddProductsTest extends TestCase
{

    use Authorization;

    /**
     * @throws \AshAllenDesign\LaravelExchangeRates\Exceptions\InvalidCurrencyException
     * @throws \AshAllenDesign\LaravelExchangeRates\Exceptions\InvalidDateException
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function testAddProductsToDatabase(){

        $this->authAsFirst();

        $settingRepository = new SettingRepository();
        $settingRepository->updateOrCreateValue([
            'xml_url' => env('XML_URL'),
            'debug' => false,
            'add_products_cron' => '',
            'owner_supervisor_uuid' => Auth::user()->owner_uuid,
            'update_exist_images_disk' => false,
            'max_width_images_disk' => 800,
            'limit_products' => '10',

        ]);

        DelivererLogger::listen(function($message){
            Log::debug($message);
        });

        $addProducts = new AddProducts();

        $steps = iterator_to_array( $addProducts->addProducts(), false);

        $sizeSteps = sizeof($steps);

        $this->assertGreaterThan(1, $sizeSteps);
    }
}

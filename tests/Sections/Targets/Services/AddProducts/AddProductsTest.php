<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Targets\Services\AddProducts;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\DelivererAgrip\Sections\Sources\Services\AssignNumberEan;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts\AddProducts;
use NetLinker\DelivererAgrip\Tests\Helpers\Authorization;
use NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\AssignNumberEanTest;
use NetLinker\DelivererAgrip\Tests\TestCase;
use NetLinker\DelivererAgrip\Tests\WatchBrowser;

class AddProductsTest extends TestCase
{

    use Authorization;

    public function testAddProductsToDatabase(){

        $this->authAsFirst();

        AssignNumberEan::$fileAssignedNumbersEans = AssignNumberEanTest::FILE_ASSIGNED_NUMBER_EANS;

        WatchBrowser::addCategory();

        $settingRepository = new SettingRepository();
        $settingRepository->updateOrCreateValue([
            'login' => env('LOGIN'),
            'pass' => env('PASS'),
            'debug' => false,
            'add_products_cron' => '',
            'owner_supervisor_uuid' => Auth::user()->owner_uuid,
            'update_exist_images_disk' => false,
            'max_width_images_disk' => 800,
            'limit_products' => '100',
        ]);

        DelivererLogger::listen(function($message){
            Log::debug($message);
        });

        $addProducts = new AddProducts();

        $steps = iterator_to_array( $addProducts->addProducts(), false);

        $sizeSteps = sizeof($steps);

        $this->assertGreaterThan(1, $sizeSteps);

        File::delete(AssignNumberEan::$fileAssignedNumbersEans);
    }
}

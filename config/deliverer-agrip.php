<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Name
    |--------------------------------------------------------------------------
    |
    | Name deliverer for display in UI
    |
    */

    'name' => 'Agrip',

    /*
    |--------------------------------------------------------------------------
    | User
    |--------------------------------------------------------------------------
    |
    | Owner class for automation add owner to model.
    |
    */

    'model' => 'App\User',

    /*
    |--------------------------------------------------------------------------
    | Owner
    |--------------------------------------------------------------------------
    |
    | Owner class for automation add owner to model.
    |
    */

    'owner' => [
        'model' => 'App\Owner',
        'field_auth_user_owner_uuid' => 'owner_uuid',
    ],

    /*
   |--------------------------------------------------------------------------
   | Domain
   |--------------------------------------------------------------------------
   |
   | Route domain for module DelivererAgrip. If null, domain will be
   | taken from `app.url` config.
   |
   */

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Prefix
    |--------------------------------------------------------------------------
    |
    | Route prefix for module DelivererAgrip.
    |
    */

    'prefix' => 'deliverer-agrip',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache store for data as cookies auth
    |
    */
    'cache' => null,

    /*
    |--------------------------------------------------------------------------
    | Jobs
    |--------------------------------------------------------------------------
    |
    | Classes of jobs for module AutoDelivererStore
    |
    */

    'jobs' => [
        'add_products' => \NetLinker\DelivererAgrip\Sections\Jobs\Jobs\AddProductsJob::class,
        'update_products' => \NetLinker\DelivererAgrip\Sections\Jobs\Jobs\UpdateProductsJob::class,
        'add_shop_products' => \NetLinker\DelivererAgrip\Sections\Jobs\Jobs\AddShopProductsJob::class,
        'update_shop_products' => \NetLinker\DelivererAgrip\Sections\Jobs\Jobs\UpdateShopProductsJob::class,
        'update_my_prices_stocks' =>  \NetLinker\DelivererAgrip\Sections\Jobs\Jobs\UpdateMyPricesStocksJob::class,
        'update_prices_stocks' =>  \NetLinker\DelivererAgrip\Sections\Jobs\Jobs\UpdatePricesStocksJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatters
    |--------------------------------------------------------------------------
    |
    | Classes of formatters for module WideStore
    |
    */

    'formatters' => [
        'resource' => \NetLinker\DelivererAgrip\Sections\Formatters\Resources\Formatter::class,
        'repository' => \NetLinker\DelivererAgrip\Sections\Formatters\Repositories\FormatterRepository::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurations
    |--------------------------------------------------------------------------
    |
    | Classes of configurations for module WideStore
    |
    */

    'configurations' => [
        'resource' => \NetLinker\DelivererAgrip\Sections\Configurations\Resources\Configuration::class,
        'repository' => \NetLinker\DelivererAgrip\Sections\Configurations\Repositories\ConfigurationRepository::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Controllers
    |--------------------------------------------------------------------------
    |
    | Namespaces for controllers.
    |
    */

    'controllers' => [

        'assets' => 'NetLinker\DelivererAgrip\Sections\Assets\Controllers\AssetController',

        'dashboard' => 'NetLinker\DelivererAgrip\Sections\Dashboard\Controllers\DashboardController',

        'configurations' => 'NetLinker\DelivererAgrip\Sections\Configurations\Controllers\ConfigurationController',

        'introductions'=> 'NetLinker\DelivererAgrip\Sections\Introductions\Controllers\IntroductionController',

        'settings' => 'NetLinker\DelivererAgrip\Sections\Settings\Controllers\SettingController',

        'formatters' => 'NetLinker\DelivererAgrip\Sections\Formatters\Controllers\FormatterController',

        'formatter_ranges' => 'NetLinker\DelivererAgrip\Sections\FormatterRanges\Controllers\FormatterRangeController',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    |
    | Name queues
    |
    */

    'queues' => [],

];
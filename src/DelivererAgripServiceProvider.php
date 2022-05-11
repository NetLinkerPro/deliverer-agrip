<?php

namespace NetLinker\DelivererAgrip;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use NetLinker\DelivererAgrip\Sections\Settings\Boot\Validators\CronValidator;

class DelivererAgripServiceProvider extends ServiceProvider
{

    use EventMap;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerEvents();

        $this->registerValidators();

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'deliverer-agrip');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'deliverer-agrip');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }


    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/deliverer-agrip.php', 'deliverer-agrip');

        // Register the service the package provides.
        $this->app->singleton('deliverer-agrip', function ($app) {
            return new DelivererAgrip();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['deliverer-agrip'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {

        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config/deliverer-agrip.php' => config_path('deliverer-agrip.php'),
        ], 'config');

        // Publishing the views.
        $this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/deliverer-agrip'),
        ], 'views');

        // Publishing assets.
        $this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/deliverer-agrip'),
        ], 'views');

        // Publishing the translation files.
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/deliverer-agrip'),
        ], 'views');

        // Registering package commands.
        $this->commands([]);
    }

    /**
     * Register the Horizon job events.
     *
     * @return void
     */
    protected function registerEvents()
    {
        $events = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    /**
     * Register validators
     */
    private function registerValidators()
    {
        (new CronValidator())->boot();
    }
}

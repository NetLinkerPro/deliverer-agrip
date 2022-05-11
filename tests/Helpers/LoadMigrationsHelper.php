<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


trait LoadMigrationsHelper
{
    /**
     * Load migrations
     */
    public function loadMigrations(){
        $this->loadMigrationsFrom(__DIR__ . '/../../vendor/netlinker/fair-queue/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../vendor/netlinker/lead-allegro/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../vendor/netlinker/wide-store/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadLaravelMigrations();
    }
}
<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


trait LoadFactoriesHelper
{
    /**
     * Load factories
     */
    public function loadFactories(){
        $this->withFactories(__DIR__ . '/../database/factories');
        $this->withFactories(__DIR__ . '/../../vendor/netlinker/fair-queue/database/factories');
    }
}
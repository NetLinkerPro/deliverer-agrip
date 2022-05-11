<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


trait SetupHelper
{

    use LoadMigrationsHelper, LoadFactoriesHelper, ClearSystemHelper;

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->loadMigrations();
        $this->loadFactories();
        $this->clearSystem();
    }
}
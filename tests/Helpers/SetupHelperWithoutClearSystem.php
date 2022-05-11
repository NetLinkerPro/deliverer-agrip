<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


trait SetupHelperWithoutClearSystem
{

    use LoadMigrationsHelper, LoadFactoriesHelper;

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->loadMigrations();
        $this->loadFactories();
    }
}
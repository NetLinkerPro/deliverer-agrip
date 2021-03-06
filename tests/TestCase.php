<?php

namespace NetLinker\DelivererAgrip\Tests;

use NetLinker\DelivererAgrip\Tests\Helpers\AssertHelper;
use Orchestra\Testbench\TestCase as TestBench;

abstract class TestCase extends TestBench
{
    use TestHelper, AssertHelper;

    protected const TEST_APP_TEMPLATE = __DIR__.'/../testbench/template';
    protected const TEST_APP = __DIR__.'/../testbench/laravel';

    public static function setUpBeforeClass():void
    {
        if (! file_exists(self::TEST_APP_TEMPLATE)) {
            self::setUpLocalTestbench();
        }
        parent::setUpBeforeClass();
    }

    protected function getBasePath()
    {
        return self::TEST_APP;
    }

    /**
     * Setup before each test.
     */
    public function setUp(): void
    {
        $this->installTestApp();
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        TestHelper::getEnvironmentSetUp($app);

    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void
    {
        $this->uninstallTestApp();
        parent::tearDown();
    }


    /**
     * Tell Testbench to use this package.
     *
     * @param $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return TestHelper::getPackageProviders($app);
    }


    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return TestHelper::getPackageAliases($app);
    }
}

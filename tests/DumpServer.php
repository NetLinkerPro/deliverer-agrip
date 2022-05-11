<?php

namespace NetLinker\DelivererAgrip\Tests;

use Carbon\Carbon;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use NetLinker\DelivererAgrip\Sections\Accounts\Models\Account;
use NetLinker\DelivererAgrip\Sections\Applications\Models\Application;
use NetLinker\DelivererAgrip\Tests\Stubs\Owner;
use NetLinker\DelivererAgrip\Tests\Stubs\User;

class DumpServer extends TestCase
{


    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../vendor/netlinker/fair-queue/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->withFactories(__DIR__ . '/database/factories');
        $this->loadLaravelMigrations();

        Artisan::call('cache:clear');
        Redis::command('flushdb');
    }


    /**
     * @test
     *
     * @throws \Throwable
     */
    public function dumpServer()
    {
        Artisan::call('dump-server');
    }

}

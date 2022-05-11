<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;

trait ClearSystemHelper
{

    /** @var bool $withoutClearSystem */
    protected $withoutClearSystem = false;

    /**
     * Clear system
     */
    public function clearSystem(){
        if (!$this->withoutClearSystem){
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Redis::command('flushdb');
        }
    }
}
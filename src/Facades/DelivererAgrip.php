<?php

namespace NetLinker\DelivererAgrip\Facades;

use Illuminate\Support\Facades\Facade;

class DelivererAgrip extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'deliverer-agrip';
    }
}

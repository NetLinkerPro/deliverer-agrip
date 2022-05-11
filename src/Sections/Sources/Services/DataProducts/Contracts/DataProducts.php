<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts;


use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;

interface DataProducts
{
    /**
     * Get
     *
     * @param ProductSource|null $product
     * @return Generator|ProductSource[]
     */
    public function get(?ProductSource $product = null):Generator;
}
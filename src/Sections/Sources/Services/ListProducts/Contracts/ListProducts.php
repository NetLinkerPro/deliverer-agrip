<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts;


use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;

interface ListProducts
{

    /**
     * Get
     *
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     */
    public function get(?CategorySource $category = null):Generator;
}
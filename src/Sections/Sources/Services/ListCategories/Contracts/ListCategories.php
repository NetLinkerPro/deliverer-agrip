<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts;


use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;

interface ListCategories
{
    /**
     * Get
     *
     * @return Generator|CategorySource[]|array
     */
    public function get():Generator;
}
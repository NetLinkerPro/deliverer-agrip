<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Repositories\Contracts;


use Generator;

interface LiveRepository
{
    /**
     * Get products
     *
     * @return Generator
     */
    public function get(): Generator;
}
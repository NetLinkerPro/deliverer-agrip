<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Repositories;

use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\Contracts\LiveRepository as LiveRepositoryContract;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\LiveDotnetnukeListProducts;

class LiveRepository implements LiveRepositoryContract
{

    /** @var int $counterProducts */
    protected $counterProducts;

    /** @var ListProducts $listProducts */
    protected $listProducts;

    /**
     * ProductRepository constructor
     *
     * @param array $configuration
     */
    public function __construct(array $configuration = [])
    {
        $this->listProducts = app(LiveDotnetnukeListProducts::class, [
            'login' =>$configuration['login'],
            'password' =>$configuration['pass'],
        ]);
    }

    /**
     * Get products
     *
     * @return Generator
     */
    public function get(): Generator
    {
        $products = $this->getProducts();
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get products
     *
     * @return Generator|void
     */
    private function getProducts()
    {
        $this->counterProducts = 0;
        $products = $this->listProducts->get();
        foreach ($products as $product) {
            yield $product;
            $this->counterProducts++;
        }
    }
}
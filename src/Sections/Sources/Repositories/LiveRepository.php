<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Repositories;

use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\Contracts\LiveRepository as LiveRepositoryContract;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Comarch2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Ftp2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Prestashop2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlSkyshopListProducts;

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
        $this->listProducts = app(Comarch2ListProducts::class, [
            'login' =>$configuration['login'],
            'password' =>$configuration['pass'],
            'login2' =>$configuration['login2'],
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
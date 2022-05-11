<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Repositories\Archives\Symfony;

use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\Contracts\LiveRepository as LiveRepositoryContract;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Magento2DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\SoapDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\AspListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Magento2ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SymfonyListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AspListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Magento2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SoapListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SymfonyListProducts;

class LiveRepository implements LiveRepositoryContract
{

    /** @var int $counterProducts */
    protected $counterProducts;

    /** @var ListCategories $listCategories */
    protected $listCategories;

    /** @var ListProducts $listProducts */
    protected $listProducts;

    /**
     * ProductRepository constructor
     *
     * @param array $configuration
     */
    public function __construct(array $configuration = [])
    {
        $this->listCategories = app(SymfonyListCategories::class, [
            'login' => $configuration['login'],
            'password' => $configuration['pass'],
        ]);
        $this->listProducts = app(SymfonyListProducts::class, [
            'login' => $configuration['login'],
            'password' => $configuration['pass'],
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
        $categories = $this->listCategories->get();
        foreach ($categories as $category) {
            $rawProducts = $this->listProducts->get($category);
            foreach ($rawProducts as $rawProduct) {
                yield $rawProduct;
                $this->counterProducts++;
            }
        }

    }
}
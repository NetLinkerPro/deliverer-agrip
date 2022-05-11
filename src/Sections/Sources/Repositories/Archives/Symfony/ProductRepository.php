<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Repositories\Archives\Symfony;

use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\Contracts\ProductRepository as ProductRepositoryContract;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\AspDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\SoapDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\SymfonyDataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\AspListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SymfonyListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\AspListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SoapListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SymfonyListProducts;

class ProductRepository implements ProductRepositoryContract
{
    const FROM_ADD_PRODUCT_FORMAT = 'id_main_category;number_page_category';

    /** @var array $excludeProducts */
    protected $excludeProducts = [];

    /** @var $limitProducts */
    protected $limitProducts;

    /** @var int $counterProducts */
    protected $counterProducts;

    /** @var ListCategories $listCategories */
    protected $listCategories;

    /** @var ListProducts $listProducts */
    protected $listProducts;

    /** @var DataProducts $dataProducts */
    protected $dataProducts;

    /** @var string|null $fromAddProduct */
    protected $fromAddProduct;

    /**
     * ProductRepository constructor
     *
     * @param array $configuration
     */
    public function __construct(array $configuration = [])
    {
        $this->limitProducts = $configuration['limit_products'] ?? null;
        $this->excludeProducts = $configuration['exclude_products'] ?? [];
        $this->fromAddProduct = $configuration['from_add_product'] ?? null;
        $this->listCategories = app(SymfonyListCategories::class, [
            'login' => $configuration['login'],
            'password' => $configuration['pass'],
        ]);
        $this->listProducts = app(SymfonyListProducts::class, [
            'login' => $configuration['login'],
            'password' => $configuration['pass'],
        ]);
        $this->dataProducts = app(SymfonyDataProducts::class, [
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
                if (!$this->isExcludeProduct($rawProduct)) {
                    $products = $this->dataProducts->get($rawProduct);
                    foreach ($products as $product) {
                        yield $product;
                        $this->counterProducts++;
                        if ($this->limitProducts && $this->counterProducts >= $this->limitProducts) {
                            return;
                        }
                    }
                }
            }
        }

    }

    /**
     * Is exclude product
     *
     * @param ProductSource $product
     * @return bool
     */
    private function isExcludeProduct(ProductSource $product): bool
    {
        // Not add stock = 0 or price = 0 for algorithm update products.
        return in_array($product->getId(), $this->excludeProducts);
    }
}
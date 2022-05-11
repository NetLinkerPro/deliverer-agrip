<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Repositories\Archives\Magento2;

use Generator;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\Contracts\ProductRepository as ProductRepositoryContract;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Magento2DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Magento2ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Magento2ListProducts;

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
        $this->listCategories = app(Magento2ListCategories::class);
        $this->listProducts = app(Magento2ListProducts::class, ['configuration' =>$configuration]);
        $this->dataProducts = app(Magento2DataProducts::class);
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
                $products = $this->dataProducts->get($rawProduct);
                foreach ($products as $product) {
                    if (!$this->isExcludeProduct($product)) {
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
        return in_array($product->getId(), $this->excludeProducts);
    }
}
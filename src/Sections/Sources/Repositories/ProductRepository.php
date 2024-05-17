<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Repositories;

use Generator;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\Contracts\ProductRepository as ProductRepositoryContract;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Comarch2DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Prestashop2DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Comarch2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\DotnetnukeListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Ftp2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Prestashop2ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\XmlSkyshopListProducts;
use NetLinker\WideStore\Sections\Products\Models\Product;

class ProductRepository implements ProductRepositoryContract
{
    const FROM_ADD_PRODUCT_FORMAT = 'id_main_category';

    /** @var ListProducts $listProducts */
    protected $listProducts;

    /** @var DataProducts $dataProducts */
    protected $dataProducts;

    /** @var array $excludeProducts */
    protected $excludeProducts = [];

    /** @var $limitProducts */
    protected $limitProducts;

    /** @var int $counterProducts */
    protected $counterProducts;

    /** @var string|null $fromAddProduct */
    protected $fromAddProduct;

    /** @var string $mode */
    protected $mode;

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
        $this->mode = $configuration['mode'];
        $this->listProducts = app(DotnetnukeListProducts::class, [
            'login' =>$configuration['login'],
            'password' =>$configuration['pass'],
            'login2' =>$configuration['login2'],
            'configuration' => $configuration,
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
            if ($this->fromAddProduct){
               if ($this->fromAddProduct === $product->getId()){
                   $this->fromAddProduct = null;
               }
            }
            if ($this->fromAddProduct){
                continue;
            }
            DelivererLogger::log(sprintf('Counter %s.', $this->counterProducts));
            if (!$this->isExcludeProduct($product)) {
                DelivererLogger::log(sprintf('Counter %s.', $this->counterProducts));
                yield $product;
                array_push($this->excludeProducts, $product->getId());
                $this->counterProducts++;
                if ($this->limitProducts && $this->counterProducts >= $this->limitProducts) {
                    return;
                }
            } else {
                DelivererLogger::log(sprintf('Exclude %s.', $product->getId()));
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
        if ($this->mode === 'update') {
            $productInDb = Product::where('identifier', $product->getId())
                ->where('deliverer', 'agrip')
                ->first();
            if ($productInDb) {
                if (!$product->getPrice()) {
                    $product->setPrice($productInDb->price);
                }
                return false;
            }
        }
        if (!$product->getStock()) {
            return true;
        } else if (!$product->getPrice()) {
            return true;
        }

        // Not add stock = 0 or price = 0 for algorithm update products.
        return in_array($product->getId(), $this->excludeProducts);
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\Baselinker;

use Illuminate\Support\Facades\DB;
use NetLinker\DelivererAgrip\Sections\Configurations\Models\Configuration;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Targets\Services\Baselinker\Contracts\StorageBaselinker as StorageBaselinkerContract;
use NetLinker\LaravelApiBaselinker\BaselinkerApiException;
use NetLinker\LaravelApiBaselinker\BaselinkerClient;
use NetLinker\WideStore\Sections\ShopAttributes\Models\ShopAttribute;
use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;

class StorageBaselinker implements StorageBaselinkerContract
{
    /** @var array|null $configurationBaselinker */
    private $configurationBaselinker;

    /** @var bool $isConfigurated */
    private $isConfigurated;

    /** @var BaselinkerClient $clientBaselinker */
    private $clientBaselinker;

    /** @var array $baselinkerProducts */
    private $baselinkerProducts;

    /**
     * StorageBaselinker constructor
     *
     * @param array|null $configurationBaselinker
     */
    public function __construct(?array $configurationBaselinker)
    {
        $this->configurationBaselinker = $configurationBaselinker;
    }

    /**
     * Get stock
     *
     * @param ShopProduct $shopProduct
     * @return int|null
     * @throws BaselinkerApiException
     */
    public function getStock(ShopProduct $shopProduct): ?int
    {
        if (!$this->isConfigurated()) {
            return null;
        }
        $this->initBaselinkerProducts();
        $sku = $this->getSkuProduct($shopProduct);
        if ($sku){
            return $this->baselinkerProducts[$sku]['stock'] ?? 0;
        }
        return null;
    }

    /**
     * Is configurated
     *
     * @return bool
     */
    private function isConfigurated(): bool
    {
        if ($this->isConfigurated === null) {
            $apiBaselinker = $this->configurationBaselinker['api_token'] ?? '';
            $idCategoryProducts = $this->configurationBaselinker['id_category_products'] ?? '';
            if ($apiBaselinker && $idCategoryProducts) {
                $this->isConfigurated = $apiBaselinker && $idCategoryProducts;
            } else {
                $this->isConfigurated = false;
                DelivererLogger::log('Baselinker is not configurated.');
            }
        }
        return $this->isConfigurated;
    }

    /**
     * Init Baselinker products
     *
     * @throws BaselinkerApiException
     */
    private function initBaselinkerProducts(): void
    {
        if ($this->baselinkerProducts !== null){
            return;
        }
        $client = $this->getClientBaselinker();
        $inputParameters = [
            'storage_id' => 'bl_1',
            'filter_category_id' =>$this->configurationBaselinker['id_category_products'],
        ];
        $response = $client->storages()->getProductsList($inputParameters);
        $productsList = $response->toArray()['products'];
        $pages = $response->toArray()['pages'] ?? 1;
        for ($page = 2; $page <= $pages; $page++) {
            $inputParameters['page'] = $page;
            $response = $client->storages()->getProductsList($inputParameters);
            $productsList = array_merge($productsList, $response->toArray()['products']);
        }
        $this->baselinkerProducts = [];
        foreach ($productsList as $product){
            $sku = $product['sku'];
            if ($sku){
                $existStock = $this->baselinkerProducts[$sku]['stock'] ?? 0;
                $this->baselinkerProducts[$sku]['stock'] = $product['quantity'] + $existStock;
            }
        }
    }

    /**
     * Get client Baselinker
     *
     * @return BaselinkerClient
     */
    private function getClientBaselinker(): BaselinkerClient
    {
        if (!$this->clientBaselinker) {
            $this->clientBaselinker = new BaselinkerClient(['token' => $this->configurationBaselinker['api_token']]);
        }
        return $this->clientBaselinker;
    }

    /**
     * Get SKU product
     *
     * @param ShopProduct $shopProduct
     * @return string|null
     */
    private function getSkuProduct(ShopProduct $shopProduct): ?string
    {
        $attribute = DB::table('wide_store_shop_attributes')->where('product_uuid', $shopProduct->uuid)
            ->where('name', 'SKU')->first(['value']);
        if ($attribute){
            return $attribute->value;
        }
        return null;
    }
}
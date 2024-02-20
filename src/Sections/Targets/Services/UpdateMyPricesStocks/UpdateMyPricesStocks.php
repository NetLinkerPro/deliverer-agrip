<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateMyPricesStocks;

use Carbon\Carbon;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\LiveRepository;
use NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateShopProducts\UpdateShopProducts;
use NetLinker\LaravelApiBaselinker\BaselinkerApiException;
use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;
use NetLinker\WideStore\Sections\ShopStocks\Models\ShopStock;
use NetLinker\DelivererAgrip\Sections\Targets\Services\Baselinker\StorageBaselinker;

class UpdateMyPricesStocks extends UpdateShopProducts
{

    /** @var LiveRepository */
    protected $liveRepository;

    /** @var Carbon $dateStartedUpdate */
    protected $dateStartedUpdate;

    /** @var StorageBaselinker $storageBaselinker */
    private $storageBaselinker;

    /**
     * Update my prices stocks
     *
     * @return Generator
     */
    public function updateMyPricesStocks(): Generator
    {
        $this->initializeStorageBaselinker();
        return $this->updateShopProducts();
    }

    /**
     * Update shop products
     *
     * @param null $updateStarted
     * @return Generator
     */
    public function updateShopProducts($updateStarted = null): Generator
    {
        $this->liveRepository = app(LiveRepository::class, [
            'configuration' => [
                'url_1' => $this->settings()['url_1'] ?? '',
                'url_2' => $this->settings()['url_2'] ?? '',
                'login' => $this->settings()['login'] ?? '',
                'pass' => $this->settings()['pass'] ?? '',
                'login2' => $this->settings()['login2'] ?? '',
                'pass2' => $this->settings()['pass2'] ?? '',
                'token' => $this->settings()['token'] ?? '',
                'debug' =>$this->settings()['debug'] ?? false,
            ],
        ]);
        $this->setDateStartedUpdated();
        $sizeProducts = $this->buildProductQuery()->count();
        $products = $this->liveRepository->get();
        yield [
            'progress_max' => $sizeProducts,
        ];
        $counterProducts = 0;
        foreach ($products as $product) {
            $this->updateMyPriceStock($product);
            $counterProducts++;
            if ($counterProducts % 20 != 1) {
                yield [
                    'progress_now' => $counterProducts,
                ];
            }
        }
        $this->setZeroNotUpdatedStocks();
    }

    /**
     * Update my price stock
     *
     * @param ProductSource $product
     * @throws BaselinkerApiException
     */
    private function updateMyPriceStock(ProductSource $product): void
    {
        /** @var Collection $shopProducts */
        $shopProducts = ShopProduct::where('shop_uuid', $this->shopUuid)
            ->where('identifier', $product->getId())
            ->get();
        $shopProducts = $shopProducts->merge(ShopProduct::where('shop_uuid', $this->shopUuid)
            ->where('identifier', '0_'.$product->getId())
            ->get());
        if (!$shopProducts->count()) {
            DelivererLogger::log('Brak produktów '.$product->getId().' w bazie');
            return;
        }
        /** @var ShopProduct $shopProduct */
        foreach ($shopProducts as $shopProduct){
            if ($product->getPrice()) {
                $shopProduct->price = $product->getPrice();
            } else {
                DelivererLogger::log(sprintf('Product has not price: %s', $product->getId()));
            }
            if ($product->getTax()) {
                $shopProduct->tax = $product->getTax();
            }
            $shopProduct->updated_at = now();
            $shopProduct->save();
            $dataStock = [
                'stock' => $product->getStock(),
                'updated_at' => now(),
            ];
            $stockBaselinker = $this->storageBaselinker->getStock($shopProduct);
            if ($stockBaselinker){
                $dataStock['stock']+=$stockBaselinker;
            }
            if ($product->getAvailability()) {
                $dataStock['availability'] = $product->getAvailability();
            }
            ShopStock::where('shop_uuid', $this->shopUuid)
                ->where('product_uuid', $shopProduct->uuid)
                ->update($dataStock);
        }
    }


    /**
     * Add product
     *
     * @param Model $productSource
     */
    public function addProduct(Model $productSource)
    {
        // disable for update from data live
    }

    /**
     * Build product Query
     *
     * @return Builder
     */
    private function buildProductQuery(): Builder
    {
        return ShopProduct::where('shop_uuid', $this->shopUuid)
            ->where('complete', true);
    }

    /**
     * Set zero not updated stocks
     */
    private function setZeroNotUpdatedStocks()
    {
        $dateStartedUpdateString = $this->dateStartedUpdate->toDateTimeString();
        $stocks = ShopStock::where('shop_uuid', $this->shopUuid)->where('updated_at', '<', $dateStartedUpdateString)->cursor();
        foreach ($stocks as $stock) {
            $stock->stock = 0;
            $stock->updated_at = now();
            $stock->save();
        }
    }

    /**
     * Set date started updated
     */
    private function setDateStartedUpdated(): void
    {
        $dateStartedUpdate = Carbon::now();
        $dateStartedUpdate->subSeconds(5);
        $this->dateStartedUpdate = $dateStartedUpdate;
    }

    /**
     * Settings
     *
     * @return array|null
     */
    public function settings(): ?array
    {
        return (new SettingRepository())->firstOrCreateValue();
    }

    /**
     * Initialize storage Baselinker
     */
    private function initializeStorageBaselinker(): void
    {
        $configurationBaselinker = $this->configuration()['baselinker'] ?? null;
        $this->storageBaselinker = app(StorageBaselinker::class, ['configurationBaselinker' =>$configurationBaselinker]);
    }
}
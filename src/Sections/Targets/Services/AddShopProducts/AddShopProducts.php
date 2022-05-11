<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Configurations\Models\Configuration;
use NetLinker\DelivererAgrip\Sections\Configurations\Repositories\ConfigurationRepository;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\WideStore\Sections\Names\Models\Name;
use NetLinker\WideStore\Sections\Products\Models\Product;
use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;
use NetLinker\WideStore\Sections\Shops\Models\Shop;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AddShopProducts
{

    /** @var string $shopUuid */
    protected $shopUuid;

    /** @var string $ownerUuid */
    protected $ownerUuid;

    /** @var Formatter $formatter */
    protected $formatter;

  /** @var Categories $categories */
  private $categories;

    /** @var Products $products */
    protected $products;

    /** @var Attributes $attributes */
    private $attributes;

    /** @var Descriptions $descriptions */
    private $descriptions;

    /** @var Images $images */
    private $images;

    /** @var Stocks $stocks */
    protected $stocks;

    /** @var string $configurationUuid */
    protected $configurationUuid;

    /**
     * Constructor
     *
     * @param $shopUuid
     * @param $ownerUuid
     * @param $configurationUuid
     * @throws DelivererAgripException
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function __construct($shopUuid, $ownerUuid, $configurationUuid)
    {
        $this->shopUuid = $shopUuid;
        $this->ownerUuid = $ownerUuid;
        $this->configurationUuid = $configurationUuid;
        $this->formatter = Formatter::where('uuid', Shop::where('uuid', $this->shopUuid)->firstOrFail()->formatter_uuid)->firstOrFail();
        $this->categories = new Categories($this->ownerUuid, $this->shopUuid, $this->formatter);
        $this->products = new Products($this->ownerUuid, $this->shopUuid, $this->formatter);
        $this->attributes = new Attributes($this->ownerUuid, $this->shopUuid, $this->formatter);
        $this->descriptions = new Descriptions($this->ownerUuid, $this->shopUuid, $this->formatter);
        $this->images = new Images($this->ownerUuid, $this->shopUuid, $this->formatter);
        $this->stocks = new Stocks($this->ownerUuid, $this->shopUuid, $this->formatter);
    }

    /**
     * Add shop product
     *
     * @return \Generator
     * @throws DelivererAgripException
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function addShopProducts(){

        yield $this->getProgressMaxStep();

        $existProducts = $this->getExistProducts();

        foreach ($this->getCursorNames() as $key => $name){

            $product = Product::where('uuid', $name->product_uuid)->firstOrFail();
            if (in_array($product->identifier, $existProducts)){
                continue;
            }

            $this->addProduct($product);

            if ($key % 20 != 1){
                yield [
                    'progress_now' => (int) $key / 20,
                ];
            }
        }
    }

    /**
     * Get progress max step
     *
     * @return array
     */
    public function getProgressMaxStep(){

        $sizeProducts = $this->getSizeProducts();

        return ['progress_max' => (int) $sizeProducts / 20];
    }

    /**
     * Get cursor products
     *
     * @return mixed
     */
    private function getCursorNames()
    {
        return $this->buildQuery()->cursor();
    }

    /**
     * Get size products
     *
     * @return mixed
     */
    private function getSizeProducts()
    {
        return $this->buildQuery()->count();
    }

    /**
     * Build query
     *
     * @return mixed
     */
    protected function buildQuery(){
        return Name::where('deliverer', 'agrip')
            ->where('lang', $this->formatter->name_lang)
            ->where('type', $this->formatter->name_type);
    }

    /**
     * Add product
     *
     * @param Model $productSource
     */
    public function addProduct(Model $productSource)
    {
        $productShopCategory = $this->categories->add($productSource);
        $productTarget = $this->products->add($productSource, $productShopCategory);
        if (!$productTarget){
            return;
        }
        $this->attributes->add($productSource, $productTarget);
        $this->descriptions->add($productSource, $productTarget);
        $this->images->add($productSource, $productTarget);
        $this->stocks->add($productSource, $productTarget);
        $this->setComplete($productTarget);
    }

    /**
     * Set complete
     *
     * @param $productTarget
     */
    public function setComplete($productTarget){
        $productTarget->complete = true;
        $productTarget->save();
    }

    /**
     * Get exist products
     */
    public function getExistProducts()
    {
        return ShopProduct::where('deliverer', 'agrip')
            ->where('complete', true)
            ->where('shop_uuid', $this->shopUuid)
            ->get(['identifier'])
            ->pluck('identifier')
            ->toArray();
    }

    /**
     * Configuration
     *
     * @return Configuration
     */
    protected function configuration()
    {
        return (new ConfigurationRepository())->firstByUuid($this->configurationUuid);
    }
}
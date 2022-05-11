<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;


use Generator;
use Illuminate\Support\Collection;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\ProductRepository;
use NetLinker\WideStore\Sections\Products\Models\Product;

class AddProducts
{

  /** @var array $settings */
  private $settings;

  /** @var int $countProducts */
  public $countProducts;

  /** @var Categories $categories */
  private $categories;

    /** @var Products $products */
    public $products;

    /** @var Attributes $attributes */
    private $attributes;

    /** @var Descriptions $descriptions */
    private $descriptions;

    /** @var Identifiers $identifiers */
    private $identifiers;

    /** @var Images $images */
    private $images;

    /** @var Names $names */
    private $names;

    /** @var Prices $prices */
    private $prices;

    /** @var Stocks $stocks */
    private $stocks;

    /** @var Taxes $taxes */
    private $taxes;

    /** @var Urls $urls */
    private $urls;

    /** @var string $mode */
    protected $mode = 'add';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->categories = new Categories();
        $this->products = new Products();
        $this->attributes = new Attributes();
        $this->descriptions = new Descriptions();
        $this->identifiers = new Identifiers();
        $this->images = new Images();
        $this->names = new Names();
        $this->prices = new Prices();
        $this->stocks = new Stocks();
        $this->taxes = new Taxes();
        $this->urls = new Urls();
    }

    /**
     * Add product
     *
     * @return Generator
     */
    public function addProducts(): Generator{
        $products = $this->getProductsSource($this->getExistProducts());
        foreach ($products as $product){
            if ($this->canAddProduct($product)){
                $this->addProduct($product);
            }
            yield ['progress_now' => $this->countProducts];
        }
    }

    /**
     * Get products source
     *
     * @param array $existProducts
     * @return Generator|ProductSource[]
     */
    protected function getProductsSource(array $existProducts): Generator
    {
        /** @var ProductRepository $productRepository */
        $productRepository = app(ProductRepository::class,[
            'configuration' => [
                'exclude_products'=>$existProducts,
                'limit_products' => $this->settings()['limit_products'] ?? null,
                'from_add_product' => $this->settings()['from_add_product'] ?? '',
                'url_1' => $this->settings()['url_1'] ?? '',
                'url_2' => $this->settings()['url_2'] ?? '',
                'login' => $this->settings()['login'] ?? '',
                'pass' => $this->settings()['pass'] ?? '',
                'login2' => $this->settings()['login2'] ?? '',
                'pass2' => $this->settings()['pass2'] ?? '',
                'token' => $this->settings()['token'] ?? '',
                'debug' => $this->settings()['debug'] ?? false,
                'mode' => $this->mode,
            ],
        ]);
        $products = $productRepository->get();
        foreach ($products as $product){
            $this->countProducts++;
            yield $product;
        }
    }

    /**
     * Add product
     *
     * @param ProductSource $product
     * @throws DelivererAgripException
     */
    private function addProduct(ProductSource $product): void
    {
        /** @var Collection $categories */
        $categories = $product->getCategories();
        if (!sizeof($categories)){
            DelivererLogger::log('Not found categories for product: '. $product->getAttributeValue('SKU') ?? $product->getId());
            return;
        }

        $categoriesDb = $this->categories->add($product);
        $productTarget = $this->products->add($product, $categoriesDb);
        $this->attributes->add($product, $productTarget);
        $this->descriptions->add($product, $productTarget);
        $this->identifiers->add($product, $productTarget);
        $this->images->add($product, $productTarget);
        $this->names->add($product, $productTarget);
        $this->prices->add($product, $productTarget);
        $this->stocks->add($product, $productTarget);
        $this->taxes->add($product, $productTarget);
        $this->urls->add($product, $productTarget);

        $this->setActiveAndComplete($product);
    }

    /**
     * Set active and complete
     *
     * @param ProductSource $product
     */
    private function setActiveAndComplete(ProductSource $product)
    {
        Product::where('deliverer', 'agrip')
            ->where('identifier', $product->getId())
            ->update([
                'active' => true,
                'complete' => true,
            ]);
    }

    /**
     * Get exist products
     *
     * @return array
     */
    public function getExistProducts(): array
    {
        return Product::where('deliverer', 'agrip')
            ->where('complete', true)
            ->get(['identifier'])
            ->pluck('identifier')
            ->toArray();
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
     * Can add product
     *
     * @param ProductSource $product
     * @return bool
     */
    private function canAddProduct(ProductSource $product): bool
    {
        return !!$product->getStock() && !!$product->getPrice();
    }

}
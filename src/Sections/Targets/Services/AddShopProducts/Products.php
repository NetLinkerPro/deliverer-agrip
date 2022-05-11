<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Login;
use NetLinker\DelivererAgrip\Sections\Sources\Services\Products\ListProduct;
use NetLinker\WideStore\Sections\Categories\Models\Category;
use NetLinker\WideStore\Sections\Identifiers\Models\Identifier;
use NetLinker\WideStore\Sections\Names\Models\Name;
use NetLinker\WideStore\Sections\Prices\Models\Price;
use NetLinker\WideStore\Sections\ShopCategories\Models\ShopCategory;
use NetLinker\WideStore\Sections\ShopProductCategories\Models\ShopProductCategory;
use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;
use NetLinker\WideStore\Sections\Taxes\Models\Tax;
use NetLinker\WideStore\Sections\Urls\Models\Url;

class Products
{

    /** @var Formatter $formatter */
    private $formatter;

    /** @var $ownerUuid */
    private $ownerUuid;

    /** @var $shopUuid */
    private $shopUuid;

    /**
     * Constructor
     *
     * @param $ownerUuid
     * @param $shopUuid
     * @param Formatter $formatter
     */
    public function __construct($ownerUuid, $shopUuid, Formatter $formatter)
    {
        $this->formatter = $formatter;
        $this->ownerUuid = $ownerUuid;
        $this->shopUuid = $shopUuid;
    }

    /**
     * Add to database
     *
     * @param Model $productSource
     * @return null
     */
    public function add(Model $productSource, ShopCategory $shopCategory)
    {
        $price = $this->getPrice($productSource);
        $tax = $this->getTax($productSource);

        $shopProduct = ShopProduct::updateOrCreate([
            'shop_uuid' => $this->shopUuid,
            'source_uuid' => $productSource->uuid,
            'owner_uuid' => $this->ownerUuid,
            'deliverer' => 'agrip',
            'identifier' =>$this->getIdentifier($productSource),
        ], [
            'category_uuid' => $shopCategory->uuid,
            'name' =>$this->getName($productSource),
            'price' => $price,
            'tax' => $tax,
            'url' => $this->getUrl($productSource),
            'complete' => false,
        ]);

        $this->addProductToCategories($shopProduct);

        return $shopProduct;

    }


    /**
     * Add product to categories
     *
     * @param $shopProduct
     */
    public function addProductToCategories($shopProduct){

        $shopCategory = $this->getShopCategory($shopProduct->category_uuid);

        do {

            ShopProductCategory::updateOrCreate([
                'owner_uuid' => $this->ownerUuid,
                'shop_uuid' => $this->shopUuid,
                'deliverer' => 'agrip',
                'product_uuid' => $shopProduct->uuid,
                'category_uuid' => $shopCategory->uuid,
            ]);

            $shopCategory = $this->getShopCategory($shopCategory->parent_uuid);

        } while ($shopCategory);

    }

    /**
     * Get shop category
     *
     * @param $uuid
     * @return mixed
     */
    public function getShopCategory($uuid){
        return ShopCategory::where('uuid', $uuid)->first();
    }

    /**
     * Get URL
     *
     * @param $productSource
     * @return mixed
     */
    public function getUrl($productSource){
        $url = Url::where('product_uuid', $productSource->uuid)
            ->where('type', $this->formatter->url_type)
            ->first();

        return optional($url)->url;
    }

    /**
     * Get tax
     *
     * @param $productSource
     * @return mixed
     */
    public function getTax($productSource){
        $taxCountry = $this->formatter->tax_country;

        if ($taxCountry === 'none'){
            return 0;
        }

        if (Str::startsWith($taxCountry, 'custom:')){
            $tax = str_replace('custom:', '', $taxCountry);
            return intval($tax);
        }

        return Tax::where('product_uuid', $productSource->uuid)
            ->where('country', $this->formatter->tax_country)
            ->first()
            ->tax ?? null;
    }

    /**
     * Get price
     *
     * @param $productSource
     * @return mixed
     */
    public function getPrice($productSource){

        return Price::where('product_uuid', $productSource->uuid)
            ->where('currency', $this->formatter->price_currency)
            ->where('type', $this->formatter->price_type)
            ->first()
            ->price ?? null;
    }

    /**
     * Get name
     *
     * @param $productSource
     * @return mixed
     */
    public function getName($productSource){
        return Name::where('product_uuid', $productSource->uuid)
            ->where('lang', $this->formatter->name_lang)
            ->where('type', $this->formatter->name_type)
            ->firstOrFail()
            ->name;
    }

    /**
     * Get identifier
     *
     * @param $productSource
     * @return mixed
     */
    public function getIdentifier($productSource, $type = null){
        return Identifier::where('product_uuid', $productSource->uuid)
            ->where('type', $type ?? $this->formatter->identifier_type)
            ->firstOrFail()
            ->identifier;
    }

}
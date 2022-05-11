<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\WideStore\Sections\Descriptions\Models\Description;
use NetLinker\WideStore\Sections\ShopDescriptions\Models\ShopDescription;

class Descriptions
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
     * @param array $productSource
     * @param Model $shopProduct
     */
    public function add(Model $productSource, Model $shopProduct)
    {
        $descriptionSource = $this->getDescription($productSource);

        ShopDescription::updateOrCreate([
            'owner_uuid'=> $this->ownerUuid,
            'shop_uuid' => $this->shopUuid,
            'deliverer' => 'agrip',
            'product_uuid' => $shopProduct->uuid,
        ], [
            'description' => $descriptionSource->description,
        ]);
    }


    /**
     * Get description
     *
     * @param $productSource
     * @return
     */
    public function getDescription($productSource)
    {
        return Description::where('product_uuid', $productSource->uuid)
            ->where('deliverer', 'agrip')
            ->where('lang', $this->formatter->description_lang)
            ->where('type', $this->formatter->description_type)
            ->firstOrFail();
    }

}
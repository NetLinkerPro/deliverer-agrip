<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts;


use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\WideStore\Sections\Attributes\Models\Attribute;
use NetLinker\WideStore\Sections\ShopAttributes\Models\ShopAttribute;

class Attributes
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
        $attributesSource = $this->getAttributes($productSource);

        foreach ($attributesSource as $attributeSource) {

            ShopAttribute::updateOrCreate([
                'owner_uuid'=> $this->ownerUuid,
                'shop_uuid' => $this->shopUuid,
                'deliverer' => 'agrip',
                'product_uuid' => $shopProduct->uuid,
                'name' => $attributeSource->name,
            ], [
                'value' => $attributeSource->value,
                'order' => $attributeSource->order,
            ]);

        }
    }

    /**
     * Get attributes
     *
     * @param $productSource
     * @return
     */
    public function getAttributes($productSource)
    {
        return Attribute::where('product_uuid', $productSource->uuid)
            ->where('deliverer', 'agrip')
            ->where('lang',$this->formatter->attribute_lang)
            ->where('type', $this->formatter->attribute_type)
            ->get();
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\WideStore\Sections\Images\Models\Image;
use NetLinker\WideStore\Sections\ShopImages\Models\ShopImage;

class Images
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
        $imagesSource = $this->getImages($productSource);

        foreach ($imagesSource as $imageSource) {

            ShopImage::updateOrCreate([
                'owner_uuid'=> $this->ownerUuid,
                'shop_uuid' => $this->shopUuid,
                'deliverer' => 'agrip',
                'product_uuid' => $shopProduct->uuid,
                'identifier' => $imageSource->identifier,
            ], [
                'url_target' => $imageSource->url_target,
                'order' => $imageSource->order,
                'main' => $imageSource->main,
            ]);

        }
    }

    /**
     * Get images
     *
     * @param $productSource
     * @return
     */
    public function getImages($productSource)
    {
        return Image::where('product_uuid', $productSource->uuid)
            ->where('deliverer', 'agrip')
            ->where('lang', $this->formatter->image_lang)
            ->where('active', true)
            ->where('type', $this->formatter->image_type)
            ->get();
    }

}
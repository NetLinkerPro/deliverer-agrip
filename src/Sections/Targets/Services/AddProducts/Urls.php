<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;


use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Urls\Models\Url;

class Urls
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        Url::updateOrCreate([
            'deliverer' => 'agrip',
            'product_uuid' => $productTarget->uuid,
            'type' => 'default'
        ],[
            'url' => $product->getUrl(),
        ]);
    }

}
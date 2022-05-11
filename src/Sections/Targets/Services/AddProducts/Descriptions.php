<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Descriptions\Models\Description;

class Descriptions
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        Description::updateOrCreate([
            'deliverer' => 'agrip',
            'product_uuid' => $productTarget->uuid,
            'lang' => $product->getLanguage(),
            'type' => 'default'
        ],[
            'description' => $product->getDescription(),
        ]);
    }

}
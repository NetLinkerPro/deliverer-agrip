<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Taxes\Models\Tax;

class Taxes
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        Tax::updateOrCreate([
            'product_uuid' => $productTarget->uuid,
            'country' => $product->getCountry(),
            'tax' => $product->getTax(),
        ]);
    }

}
<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Identifiers\Models\Identifier;

class Identifiers
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        Identifier::updateOrCreate([
            'deliverer' => 'agrip',
            'product_uuid' => $productTarget->uuid,
            'type' => 'default'
        ], [
            'identifier' => $product->getId(),
        ]);
    }

}
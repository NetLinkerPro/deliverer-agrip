<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Stocks\Models\Stock;

class Stocks
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        Stock::updateOrCreate([
            'deliverer' => 'agrip',
            'product_uuid' => $productTarget->uuid,
            'department' => 'default',
            'type' => 'default'
        ],[
            'stock' => $product->getStock(),
            'availability' => $product->getAvailability(),
        ]);
    }

}
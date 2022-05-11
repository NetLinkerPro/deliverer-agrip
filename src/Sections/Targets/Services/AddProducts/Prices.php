<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Prices\Models\Price;

class Prices
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        Price::updateOrCreate([
            'deliverer' => 'agrip',
            'product_uuid' => $productTarget->uuid,
            'currency' => $product->getCurrency(),
            'type' => 'default'
        ],[
            'price' => $product->getPrice(),
        ]);

        $priceGuest = (float) $product->getProperty('price_guest');
        if ($priceGuest){
            Price::updateOrCreate([
                'deliverer' => 'agrip',
                'product_uuid' => $productTarget->uuid,
                'currency' => $product->getCurrency(),
                'type' => 'guest'
            ],[
                'price' => $priceGuest,
            ]);
        }
    }

}
<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Names\Models\Name;

class Names
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        Name::updateOrCreate([
            'deliverer' => 'agrip',
            'product_uuid' => $productTarget->uuid,
            'lang' => $product->getLanguage(),
            'type' => 'default'
        ],[
            'name' => $product->getName(),
        ]);
        if ($product->hasProperty('name_pl')){
            $namePl = $product->getProperty('name_pl') ?: $product->getName();
            Name::updateOrCreate([
                'deliverer' => 'agrip',
                'product_uuid' => $productTarget->uuid,
                'lang' => 'pl',
                'type' => 'default'
            ],[
                'name' => $namePl,
            ]);
        }
    }
}
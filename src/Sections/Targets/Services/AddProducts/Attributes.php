<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;


use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Attributes\Models\Attribute;

class Attributes
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute){
            $name = $attribute->getName();
            $value = $attribute->getValue();
            $name = trim(str_replace(["\n", "\r", "\t"], '', $name));
            $value = trim(str_replace(["\n", "\r", "\t"], '', $value));
            if (mb_strlen($name) > 50){
                DelivererLogger::log(sprintf('Too long attribute %s', $name));
                continue;
            }
            if ($name && $value){
                if (mb_strtolower($name) === 'ean'){
                    $name = 'EAN';
                } else if (mb_strtolower($name) === 'sku'){
                    $name = 'SKU';
                }
                Attribute::updateOrCreate([
                    'product_uuid' => $productTarget->uuid,
                    'deliverer' => 'agrip',
                    'name' => $name,
                ], [
                    'value' =>$value,
                    'order' => $attribute->getOrder(),
                    'lang' => $product->getLanguage(),
                    'type' => 'default',
                ]);
            }
        }
    }
}
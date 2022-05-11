<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;


use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Categories\Models\Category;
use NetLinker\WideStore\Sections\ProductCategories\Models\ProductCategory;
use NetLinker\WideStore\Sections\Products\Models\Product;

class Products
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param array $categoriesDb
     * @return mixed
     */
    public function add(ProductSource $product, array $categoriesDb)
    {
        $category = end($categoriesDb);
        $productTarget = Product::updateOrCreate([
            'deliverer' => 'agrip',
            'identifier' => $product->getId(),
        ], [
            'category_uuid' => $category->uuid,
            'name' => $product->getName(),
            'complete' => false,
        ]);
        foreach ($categoriesDb as $category){
            ProductCategory::updateOrCreate([
                'deliverer' => 'agrip',
                'product_uuid' => $productTarget->uuid,
                'type' => 'default',
                'category_uuid' => $category->uuid,
            ]);
        }
        return $productTarget;
    }

}
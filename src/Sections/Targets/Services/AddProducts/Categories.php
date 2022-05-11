<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\WideStore\Sections\Categories\Models\Category;

class Categories
{

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @return array|Category[]
     */
    public function add(ProductSource $product): array
    {
        $categories = $product->getCategories();
        $parentUuid = null;
        $categoriesDb=[];
        do{
            $category = $categories[0];
            $categoryDb = $this->updateOrCreate($category, $parentUuid, $product);
            array_push($categoriesDb, $categoryDb);
            $parentUuid = $categoryDb->uuid;
            $categories = $category->getChildren();
        } while($categories);
        return $categoriesDb;
    }

    /**
     * Update or create
     *
     * @param CategorySource $category
     * @param string|null $parentUuid
     * @param ProductSource $product
     * @return mixed
     */
    private function updateOrCreate(CategorySource $category, ?string $parentUuid, ProductSource $product)
    {
        return Category::updateOrCreate([
            'deliverer' => 'agrip',
            'identifier' => $category->getId(),
            'parent_uuid' => $parentUuid,
            'lang' => $product->getLanguage(),
            'type' => 'default'
        ], [
            'name' => $category->getName(),
        ]);
    }
}
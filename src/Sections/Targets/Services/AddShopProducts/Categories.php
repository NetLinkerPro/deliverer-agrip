<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts;


use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\WideStore\Sections\Categories\Models\Category;
use NetLinker\WideStore\Sections\ShopCategories\Models\ShopCategory;
use NetLinker\WideStore\Sections\ShopProductCategories\Models\ShopProductCategory;

class Categories
{

    /** @var Formatter $formatter */
    private $formatter;

    /** @var $ownerUuid */
    private $ownerUuid;

    /** @var $shopUuid */
    private $shopUuid;

    /**
     * Constructor
     *
     * @param $ownerUuid
     * @param $shopUuid
     * @param Formatter $formatter
     */
    public function __construct($ownerUuid, $shopUuid, Formatter $formatter)
    {
        $this->formatter = $formatter;
        $this->ownerUuid = $ownerUuid;
        $this->shopUuid = $shopUuid;
    }

    /**
     * Add to database
     *
     * @param Model $product
     * @return ShopCategory
     */
    public function add(Model $product)
    {
        $sourceCategories = $this->getSourceCategories($product->category_uuid);
        $shopParentUuid = null;
        $productShopCategory = null;
        foreach ($sourceCategories as $sourceCategory){
            $shopCategory = ShopCategory::updateOrCreate([
                'parent_uuid' => $shopParentUuid,
                'owner_uuid' => $this->ownerUuid,
                'shop_uuid' => $this->shopUuid,
                'deliverer' => $sourceCategory->deliverer,
                'identifier' => $sourceCategory->identifier,
            ], [
                'name' => $sourceCategory->name,
            ]);
            $productShopCategory = $shopCategory;
            $shopParentUuid = $shopCategory->uuid;
        }
        return $productShopCategory;
    }

    public function addOld(Model $product)
    {
        $categoriesSource = $this->getCategories();
        $categoriesSource = $this->updateOrCreateCategories($categoriesSource);

        while ($categoriesSource->count()) {

            $categoriesSource = $this->getChildCategories($categoriesSource);
            $categoriesSource = $this->updateOrCreateCategories($categoriesSource);

        }
    }


    /**
     * Get child categories
     *
     * @param Collection $categoriesSource
     * @return Collection
     */
    public function getChildCategories(Collection $categoriesSource)
    {

        $allChildCategoriesSource = collect();

        /** @var Category $category */
        foreach ($categoriesSource as &$categorySource) {


            $childCategoriesSource = $this->getCategories($categorySource->uuid);

            foreach ($childCategoriesSource as &$childCategorySource) {
                $childCategorySource->parent_uuid_target = $categorySource->uuid_target;
            }

            $allChildCategoriesSource = $allChildCategoriesSource->merge($childCategoriesSource);

        }

        return $allChildCategoriesSource;
    }

    /**
     * Update or create
     *
     * @param Collection $categoriesSource
     * @return Collection
     */
    public function updateOrCreateCategories(Collection $categoriesSource)
    {

        /** @var Category $category */
        foreach ($categoriesSource as &$categorySource) {

            $shopCategory = ShopCategory::updateOrCreate([
                'parent_uuid' => $categorySource->parent_uuid_target ?? null,
                'owner_uuid' => $this->ownerUuid,
                'shop_uuid' => $this->shopUuid,
                'deliverer' => $categorySource->deliverer,
                'identifier' => $categorySource->identifier,
            ], [
                'name' => $categorySource->name,
            ]);

            $categorySource->uuid_target = $shopCategory->uuid;
        }

        return $categoriesSource;
    }

    /**
     * Get categories
     *
     * @param null $parentUuid
     * @return mixed
     */
    public function getCategories($parentUuid = null)
    {
        return Category::where('parent_uuid', $parentUuid)
            ->where('deliverer', 'agrip')
            ->where('lang', $this->formatter->category_lang)
            ->where('type', $this->formatter->category_type)
            ->get();
    }

    private function getRootCategory($categoryUuid)
    {
        $category = $this->getCategory($categoryUuid);
        $parentCategoryUuid = null;

        do {
            $parentCategoryUuid = $category->parent_uuid;
            if ($parentCategoryUuid){
                $category = $this->getCategory($parentCategoryUuid);
            }
        } while ($parentCategoryUuid);

        return $category;

    }

    public function getCategory($uuid)
    {
        return Category::where('uuid' , $uuid)
            ->firstOrFail();
    }

    private function getSourceCategories($categoryUuid)
    {
        $categories = collect();

        $category = $this->getCategory($categoryUuid);
        $categories->push($category);
        $parentCategoryUuid = null;

        do {
            $parentCategoryUuid = $category->parent_uuid;
            if ($parentCategoryUuid){
                $category = $this->getCategory($parentCategoryUuid);
                $categories->push($category);
            }
        } while ($parentCategoryUuid);

        $categories = $categories->reverse();
        return $categories;
    }
}
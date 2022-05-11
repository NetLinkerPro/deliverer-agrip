<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Enums\Countries;
use NetLinker\DelivererAgrip\Sections\Sources\Enums\Currencies;
use NetLinker\DelivererAgrip\Sections\Sources\Enums\Languages;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SupremisListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\SupremisB2bListProducts;

class SupremisB2bDataProducts implements DataProducts
{
    /** @var SupremisB2bListProducts $b2bListProducts */
    protected $b2bListProducts;

    /** @var SupremisListCategories $listCategories */
    protected $listCategories;

    /** @var array $dataImagesDescriptions */
    protected $dataImagesDescriptions;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     * @param string|null $fromAddProduct
     */
    public function __construct(string $login, string $password, ?string $fromAddProduct = null)
    {
        $this->b2bListProducts = app(SupremisB2bListProducts::class, [
            'login' => $login,
            'password' => $password,
            'fromAddProduct' =>$fromAddProduct,
        ]);
        $this->listCategories = app(SupremisListCategories::class);
    }

    /**
     * Get
     *
     * @param ProductSource|null $product
     * @return Generator|ProductSource[]
     * @throws DelivererAgripException
     */
    public function get(?ProductSource $product = null): Generator
    {
        $this->initDataImagesDescriptions();
        $products = $this->b2bListProducts->get();
        foreach ($products as $product) {
            $this->fillProduct($product);
            if ($product){
                yield $product;
            }
        }
    }

    /**
     * Get Id's products
     *
     * @return array
     */
    private function getIdsProducts(): array
    {
        $products = $this->webapiClient->Products()->findAll([
            'display' => '[id,active,type,reference]'
        ]);
        $products = json_decode(json_encode($products), TRUE)['products']['product'] ?? [];
        $ids = [];
        foreach ($products as $product) {
            $id = $product['id'];
            if ($product['active'] === '1' && $product['type'] === 'simple' && !in_array($id, $ids)) {
                $reference = $product['reference'] ?: null;
                if ($reference){
                    array_push($ids, (string) $id);
                }
            }
        }
        return $ids;
    }

    /**
     * Init data images and descriptions
     */
    private function initDataImagesDescriptions(): void
    {
        $this->dataImagesDescriptions = [];
        $categories = $this->listCategories->get();
        foreach ($categories as $category){
            $categoryLast = $category->getChildren()[0]->getChildren()[0] ?? $category->getChildren()[0];
            $codeProductGroup = $categoryLast->getProperty('code_product_group');
            $image = $categoryLast->getProperty('image');
            $description = $categoryLast->getProperty('description');
            $explodeCodeProductGroup = explode('/', $codeProductGroup);
            foreach ($explodeCodeProductGroup as $item){
                $item = trim($item);
                if ($item){
                    $this->dataImagesDescriptions[$item] = [
                        'code_product_group' =>$item,
                        'image' =>$image,
                        'description' =>$description,
                    ];
                }
            }
        }
    }

    /**
     * Fill product
     *
     * @param ProductSource $product
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function fillProduct(ProductSource $product): ?ProductSource
    {
        $this->addImagesProduct($product);
        $this->addAttributesProduct($product);
        $this->addDescriptionProduct($product);
        $this->removeLongAttributes($product);
        $product->check();
        return $product;
    }

    /**
     * Add attribute product
     *
     * @param ProductSource $product
     */
    private function addAttributesProduct(ProductSource $product): void
    {

    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     */
    private function addDescriptionProduct(ProductSource $product): void
    {
        $productGroupCategory = $product->getCategories()[0]->getChildren()[0] ?? null;
        $codeProductGroup = $productGroupCategory->getName() ?? null;
        $dataDescription = $this->dataImagesDescriptions[$codeProductGroup]['description'] ?? null;
        if ($dataDescription){
            $description = '<div class="description">';
            $description .= sprintf('<div class="content-section-description" id="description_extra3">%s</div>', $dataDescription);
            $description .= '</div>';
            $product->setDescription($description);
        } else {
            $product->setDescription('');
        }
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @return void
     */
    private function addImagesProduct(ProductSource $product): void
    {
        $productGroupCategory = $product->getCategories()[0]->getChildren()[0] ?? null;
        $codeProductGroup = $productGroupCategory->getName() ?? null;
        $urlImage = $this->dataImagesDescriptions[$codeProductGroup]['image'] ?? null;
        if ($urlImage){
            $product->addImage(true, $codeProductGroup, $urlImage, sprintf('%s.png', $codeProductGroup));
        } elseif ($urlImage = $product->getProperty('image')) {
            $urlImage = trim($urlImage);
            $explodeUrlImage = explode('/', $urlImage);
            $filenameUnique = $explodeUrlImage[sizeof($explodeUrlImage) - 1];
            $product->addImage(true, $codeProductGroup, $urlImage, sprintf('%s.png', $filenameUnique));
        }
    }

    /**
     * Remove long attributes
     *
     * @param ProductSource $product
     */
    private function removeLongAttributes(ProductSource $product): void
    {
        $attributes = $product->getAttributes();
        foreach ($attributes as $index => $attribute){
            if (mb_strlen($attribute->getName()) > 50){
                unset($attributes[$index]);
            }
        }
        $product->setAttributes($attributes);
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Exception;
use Generator;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\BlWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Asp2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class BlDataProducts implements DataProducts
{
    use CrawlerHtml, ExtensionExtractor, CleanerDescriptionHtml, NumberExtractor;

    /** @var array $categories */
    private $categories;

    /** @var BlWebapiClient $webapiClient */
    protected $webapiClient;

    /**
     * BlListProducts constructor
     *
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->webapiClient = app(BlWebapiClient::class, [
            'token' => $token,
        ]);
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
        if ($this->fillProduct($product)) {
            yield $product;
        }
    }

    /**
     * Fill product
     *
     * @param ProductSource $product
     * @return bool
     * @throws DelivererAgripException
     */
    private function fillProduct(ProductSource $product): bool
    {
        $dataProduct = $this->getDataProduct($product);
        if (!$dataProduct) {
            DelivererLogger::log(sprintf('Not found data product %s.', $product->getId()));
            return false;
        }
        if (!$dataProduct['text_fields']['name']) {
            return false;
        }
        $this->addNameProduct($product, $dataProduct);
        $this->addTaxProduct($product, $dataProduct);
        $this->addCategoryProduct($product, $dataProduct);
        $this->addImagesProduct($product, $dataProduct);
        $this->addAttributesProduct($product, $dataProduct);
        $this->addDescriptionProduct($product, $dataProduct);
        $product->removeLongAttributes();
        $product->check();
        return true;
    }

    /**
     * Add attribute product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     */
    private function addAttributesProduct(ProductSource $product, array $dataProduct): void
    {
        $features = $dataProduct['text_fields']['features'] ?? [];
        $lastOrder = 200;
        foreach ($features as $name => $value) {
            if ($name && $value) {
                $lastOrder += 100;
                $product->addAttribute($name, $value, $lastOrder);
            }
        }
    }

    /**
     * Get ID image product
     *
     * @param string $url
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdImageProduct(string $url): string
    {
        $explodeUrl = explode('/products/', $url);
        $id = $explodeUrl[sizeof($explodeUrl) - 1];
        $id = str_replace('/', '_', $id);
        if (!$id || Str::contains($id, ':')) {
            throw new DelivererAgripException('Invalid ID image product');
        }
        return $id;
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     */
    private function addDescriptionProduct(ProductSource $product, array $dataProduct): void
    {
        $description = '<div class="description">';
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra2"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($product, $dataProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra3">%s</div>', $descriptionWebsiteProduct);
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get description webapi product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     * @return string
     * @throws DelivererAgripException
     */
    private function getDescriptionWebsiteProduct(ProductSource $product, array $dataProduct): string
    {
        $htmlDescription = $dataProduct['text_fields']['description'] ?? '';
        if (!$htmlDescription){
            return '';
        }
        $crawlerDescription = $this->getCrawler($htmlDescription);
        $crawlerDescription->filter('.image-item')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $descriptionWebsite = $crawlerDescription->html();
        if (Str::contains($descriptionWebsite, '<body>')){
            $descriptionWebsite =  $crawlerDescription->filter('body')->html();
        }
        if ($descriptionWebsite) {
//            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
//            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * @param ProductSource $product
     * @param array $dataProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $dataProduct)
    {
        $images = $dataProduct['images'] ?? [];
        foreach ($images as $index => $url) {
            $main = sizeof($product->getImages()) === 0;
            $extension = $this->extractExtension($url, 'jpg');
            $id = $this->getIdImageProduct($url);
            $filenameUnique = $id;
            $product->addImage($main, $id, $url, $filenameUnique, $extension);
        }
    }

    /**
     * Get data product
     *
     * @param ProductSource $product
     * @return array|null
     * @throws DelivererAgripException
     */
    private function getDataProduct(ProductSource $product): ?array
    {
        DelivererLogger::log(sprintf('Get data product %s', $product->getId()));
        $data = $this->webapiClient->sendRequest('getInventoryProductsData', [
            'inventory_id' => 1154,
            'products' => [$product->getId()],
        ]);
        return $data['products'][$product->getId()] ?? null;
    }

    /**
     * Add tax product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     */
    private function addTaxProduct(ProductSource $product, array $dataProduct): void
    {
        $tax = $dataProduct['tax_rate'];
        $product->setTax($tax);
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, array $dataProduct): void
    {
        $this->initializeCategories();
        $categoryId = $dataProduct['category_id'];
        $baselinkerCategory = $this->categories[$categoryId] ?? null;
        $categories = [];
        if ($baselinkerCategory){
            if ($baselinkerCategory['parent_id'] !== 0){
                throw new DelivererAgripException('Is not supported.');
            }
            array_push($categories, new CategorySource($baselinkerCategory['category_id'], $baselinkerCategory['name'], sprintf('https://panel.baselinker.com/category/%s', $baselinkerCategory['category_id'])));
//            $breadcrumbs = $jsonProduct['details']['model']['breadCrumb'];
//            foreach ($breadcrumbs as $breadcrumb) {
//                $name = $breadcrumb['name'];
//                $id = $breadcrumb['id'];
//                $url = $breadcrumb['url'];
//                if ($url) {
//                    $category = new CategorySource($id, $name, $url);
//                    array_push($categories, $category);
//                }
//            }
        } else {
            array_push($categories, new CategorySource('1556738', 'SPSTORE', 'https://panel.baselinker.com/category/1556738'));
        }
        if (!$categories) {
            array_push($categories, new CategorySource('pozostale', 'Pozostale', 'https://www.agrip.com/pl-pl'));
        }
        $categories = array_reverse($categories);
        $categoryProduct = null;
        /** @var CategorySource $category */
        foreach ($categories as $category) {
            if (!$categoryProduct) {
                $categoryProduct = $category;
            } else {
                $category->addChild($categoryProduct);
                $categoryProduct = $category;
            }
        }
        $product->addCategory($categoryProduct);
    }

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     */
    private function addNameProduct(ProductSource $product, array $dataProduct): void
    {
        $name = Str::limit($dataProduct['text_fields']['name'], 255, '');
        if (strlen($name) > 255){
            $name = Str::limit($name, 240, '');
        }
        $product->setName($name);
    }

    /**
     * Initialize categories
     *
     * @throws DelivererAgripException
     */
    private function initializeCategories(): void
    {
        if (!$this->categories){
            $data = $this->webapiClient->sendRequest('getInventoryCategories', [
                'inventory_id' => 1154,
            ]);
            foreach ($data['categories'] as $category) {
                $this->categories[$category['category_id']] = [
                    'category_id' =>$category['category_id'],
                    'name' =>$category['name'],
                    'parent_id'=>$category['parent_id'],
                ];
            }
        }
    }
}

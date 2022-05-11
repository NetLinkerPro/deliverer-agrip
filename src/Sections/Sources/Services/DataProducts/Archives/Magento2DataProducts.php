<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;


use Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Magento2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class Magento2DataProducts implements DataProducts
{

    use CrawlerHtml, CleanerDescriptionHtml, NumberExtractor, ExtensionExtractor;

    /** @var WebsiteClient $websiteClient */
    private $websiteClient;

    /**
     * Magento2ListCategories constructor
     */
    public function __construct()
    {
        $this->websiteClient = app(Magento2WebsiteClient::class);
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
        $crawlerWebsite = $this->getCrawlerWebsite($product);
        if ($this->hasVariants($crawlerWebsite)) {
            $products = $this->getProductsVariants($product, $crawlerWebsite);
            foreach ($products as $product) {
                yield $product;
            }
        } else {
            $product = $this->getProductSingle($product, $crawlerWebsite);
            if ($product) {
                yield $product;
            }
        }
    }

    /**
     * Get crawler website
     *
     * @param ProductSource $product
     * @return Crawler
     */
    private function getCrawlerWebsite(ProductSource $product): Crawler
    {
        $contentWebsite = $this->websiteClient->getContentAnonymous($product->getUrl());
        return $this->getCrawler($contentWebsite);
    }

    /**
     * Get data script product
     *
     * @param Crawler $crawlerWebsite
     * @return array
     * @throws DelivererAgripException
     */
    private function getDataScriptProduct(Crawler $crawlerWebsite): array
    {
        $dataScriptProduct = [];
        $crawlerWebsite->filter('script[type="application/ld+json"]')
            ->each(function (Crawler $script) use (&$dataScriptProduct) {
                $contentScript = $script->text();
                $dataScript = json_decode($contentScript, true);
                if ($dataScript['@type'] === 'Product') {
                    $dataScriptProduct = $dataScript;
                }
            });
        if (!$dataScriptProduct) {
            throw new DelivererAgripException('Not found data script product');
        }
        return $dataScriptProduct;
    }

    /**
     * Add attribute product
     *
     * @param ProductSource $product
     * @param Crawler $container
     */
    private function addAttributesProduct(ProductSource $product, Crawler $container): void
    {
        $container->filter('#product-attribute-specs-table tr')
            ->each(function (Crawler $tr, $index) use (&$product) {
                $nameAttribute = $this->getTextCrawler($tr->filter('th'));
                $valueAttribute = $this->getTextCrawler($tr->filter('td'));
                $orderAttribute = ($index + 1) * 100;
                if ($nameAttribute && $valueAttribute) {
                    $nameAttribute = str_replace(['Kod produktu', 'Marka', 'Kod EAN'], ['SKU', 'Producent', 'EAN'], $nameAttribute);
                    if ($nameAttribute === 'Producent') {
                        $orderAttribute = 10;
                    }
                    $product->addAttribute($nameAttribute, $valueAttribute, $orderAttribute);
                }
            });
    }

    /**
     * Get data variants
     *
     * @param Crawler $crawlerWebsite
     * @return array
     * @throws DelivererAgripException
     */
    private function getDataVariants(Crawler $crawlerWebsite): array
    {
        $dataVariants = [];
        $crawlerWebsite->filter('script[type="text/x-magento-init"]')
            ->each(function (Crawler $script) use (&$dataVariants) {
                $contentScript = $script->html();
                $dataScript = json_decode($contentScript, true);
                $dataScript = $dataScript['[data-role=swatch-options]']['Magento_Swatches/js/swatch-renderer']['jsonConfig'] ?? [];
                if ($dataScript) {
                    $dataVariants = $dataScript;
                }
            });
        if (!$dataVariants) {
            throw new DelivererAgripException('Not found data variants product');
        }
        return $dataVariants;
    }

    /**
     * Product has variants
     *
     * @param Crawler $crawlerWebsite
     * @return bool
     */
    private function hasVariants(Crawler $crawlerWebsite): bool
    {
        return $crawlerWebsite->filter('div.swatch-attribute-options')->count() > 0;
    }

    /**
     * Get products variants
     *
     * @param ProductSource $defaultProduct
     * @param Crawler $crawlerWebsite
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsVariants(ProductSource $defaultProduct, Crawler $crawlerWebsite): array
    {
        $dataScript = $this->getDataScriptProduct($crawlerWebsite);
        $dataVariants = $this->getDataVariants($crawlerWebsite);
        $idsVariants = $this->getIdsVariants($dataVariants);
        $productsVariants = [];
        foreach ($idsVariants as $idVariant) {
            $product = $this->getProductVariant($defaultProduct, $dataScript, $dataVariants, $idVariant, $crawlerWebsite);
            if ($product) {
                array_push($productsVariants, $product);
            }
        }
        return $productsVariants;
    }

    /**
     * Get product single
     *
     * @param ProductSource $product
     * @param Crawler $crawlerWebsite
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function getProductSingle(ProductSource $product, Crawler $crawlerWebsite): ?ProductSource
    {
        $priceNetto = (float)$crawlerWebsite->filter('meta[property="product:pretax_price:amount"]')->attr('content');
        $priceBrutto = (float)$crawlerWebsite->filter('meta[property="product:price:amount"]')->attr('content');;
        $tax = intval((round(($priceBrutto / $priceNetto), 2) * 100) - 100);
        if (!$priceNetto || $tax === null) {
            return null;
        }
        $dataScript = $this->getDataScriptProduct($crawlerWebsite);
        $stock = $this->getStockProduct($crawlerWebsite);
        $availability = 1;
        $name = $dataScript['name'];
        $product->setPrice($priceNetto);
        $product->setTax($tax);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $this->addCategoryProduct($product, $crawlerWebsite);
        $product->setName($name);
        $this->addImagesProduct($product, $dataScript);
        $this->addAttributesProduct($product, $crawlerWebsite->filter('#product-attribute-specs-table'));
        $this->addDescriptionProduct($product, $crawlerWebsite);
        $product->check();
        return $product;
    }

    /**
     * Get ID's variants
     *
     * @param array $dataVariants
     * @return array
     * @throws DelivererAgripException
     */
    private function getIdsVariants(array $dataVariants): array
    {
        $idsVariants = array_keys($dataVariants['optionPrices']);
        if (!$idsVariants) {
            throw new DelivererAgripException('Not found ID\'s variants product');
        }
        return $idsVariants;
    }

    /**
     * Get product variant
     *
     * @param ProductSource $defaultProduct
     * @param array $dataScript
     * @param array $dataVariants
     * @param int $idVariant
     * @param Crawler $crawlerWebsite
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function getProductVariant(ProductSource $defaultProduct, array $dataScript,  array $dataVariants, int $idVariant, Crawler $crawlerWebsite): ?ProductSource
    {
        $id = sprintf('%s__%s', $defaultProduct->getId(), $idVariant);
        $priceNetto = $dataVariants['optionPrices'][$idVariant]['basePrice']['amount'] ?? null;
        $priceBrutto = $dataVariants['optionPrices'][$idVariant]['finalPrice']['amount'] ?? null;
        $tax = intval((($priceBrutto / $priceNetto) * 100) - 100);
        if (!$priceNetto || $tax === null) {
            return null;
        }
        $stock = $dataVariants['product_information'][$idVariant]['qty'];
        $availability =1;
        $name = $dataVariants['product_information'][$idVariant]['name']['value'];
        $product = new ProductSource($id, $defaultProduct->getUrl());
        $product->setPrice($priceNetto);
        $product->setTax($tax);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $this->addCategoryProduct($product, $crawlerWebsite);
        $product->setName($name);
        $this->addImagesVariantProduct($product, $dataScript, $dataVariants, $idVariant);
        $this->addAttributesVariantProduct($product, $dataVariants, $idVariant);
        $this->addDescriptionProduct($product, $crawlerWebsite);
        $product->check();
        return $product;
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerWebsite
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, Crawler $crawlerWebsite): void
    {
        $categories = [];
        $crawlerWebsite->filter('ul.cs-breadcrumbs__list > li')
            ->each(function (Crawler $li) use (&$categories) {
                $classLi = $li->attr('class');
                if (Str::contains($classLi, ' category')) {
                    $id = (string)explode(' category', $classLi)[1];
                    $name = $this->getTextCrawler($li->filter('a'));
                    $url = $this->getAttributeCrawler($li->filter('a'), 'href');
                    $category = new CategorySource($id, $name, $url);
                    array_push($categories, $category);
                }
            });
        if (!$categories) {
            throw new DelivererAgripException('Not found category product');
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
     * Add images variant product
     *
     * @param ProductSource $product
     * @param array $dataScript
     * @param array $dataVariants
     * @param int $idVariant
     * @throws DelivererAgripException
     */
    private function addImagesVariantProduct(ProductSource $product, array $dataScript, array $dataVariants, int $idVariant)
    {
        $images = $dataVariants['images'][$idVariant] ?? $dataScript['image'] ?? [];
        $countImages = 0;
        foreach ($images as $image) {
            $main = $countImages === 0;
            $countImages++;
            $url = $image['full'] ?? $image;
            $filenameUnique = $this->getUniqueFilenameImageProduct($url, $countImages, $product);
            $id = $filenameUnique;
            $product->addImage($main, $id, $url, $filenameUnique);
        }
    }

    /**
     * Get unique filename image product
     *
     * @param string $url
     * @param int $countImage
     * @param ProductSource $product
     * @return string
     * @throws DelivererAgripException
     */
    private function getUniqueFilenameImageProduct(string $url, int $countImage, ProductSource $product): string
    {
        if (Str::contains($url, '1000x1000/')){
            $explodeUrl = explode('1000x1000/', $url);
        } else {
            $explodeUrl = explode('product/', $url);
        }
        $uniqueFilename = $explodeUrl[1] ?? '';
        $uniqueFilename = str_replace(['/'], '', $uniqueFilename);
        if (!$uniqueFilename || Str::contains($uniqueFilename, ':')) {
            throw new DelivererAgripException('Invalid unique filename image product');
        }
        if (mb_strlen($uniqueFilename) > 50){
            $extension = $this->extractExtension($url, 'jpg');
            $uniqueFilename = sprintf('%s___%s.%s', $product->getId(), $countImage, $extension);
         }
        return $uniqueFilename;
    }

    /**
     * Add attributes variant product
     *
     * @param ProductSource $product
     * @param array $dataVariants
     * @param int $idVariant
     */
    private function addAttributesVariantProduct(ProductSource $product, array $dataVariants, int $idVariant)
    {
        $contentAttributes = $dataVariants['product_information'][$idVariant]['attributes']['value'];
        $container = $this->getCrawler($contentAttributes);
        $this->addAttributesProduct($product, $container);
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerWebsite
     */
    private function addDescriptionProduct(ProductSource $product, Crawler $crawlerWebsite): void
    {
        $description = '<div class="description">';
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div id="description_extra2"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($product, $crawlerWebsite);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div id="description_extra3">%s</div>', $descriptionWebsiteProduct);
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get description website product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerWebsite
     * @return string
     */
    private function getDescriptionWebsiteProduct(ProductSource $product, Crawler $crawlerWebsite): string
    {
        $descriptionWebsite = $crawlerWebsite->filter('#description div.product.attribute.description > div.value')->html();
        if ($descriptionWebsite) {
            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * Get stock product
     *
     * @param Crawler $crawlerWebsite
     * @return int
     */
    private function getStockProduct(Crawler $crawlerWebsite): int
    {
        $textAvailability = $this->getTextCrawler($crawlerWebsite->filter('span.cs-buybox__stock-text'));
        if (!Str::contains($textAvailability, 'W magazynie')) {
            return 0;
        }
        $textAvailability = $this->getTextCrawler($crawlerWebsite->filter('span.cs-indicator-low-stock__label'));
        if ($textAvailability) {
            $numberStock = $this->extractInteger($textAvailability);
            if ($numberStock){
                return $numberStock;
            }
        }
        return 5;
    }

    /**
     * @param ProductSource $product
     * @param array $dataScript
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $dataScript)
    {
        $images = $dataScript['image'] ?? [];
        $countImages = 0;
        foreach ($images as $image) {
            $main = $countImages === 0;
            $countImages++;
            $url = $image;
            $filenameUnique = $this->getUniqueFilenameImageProduct($url, $countImages, $product);
            $id = $filenameUnique;
            $product->addImage($main, $id, $url, $filenameUnique);
        }
    }
}
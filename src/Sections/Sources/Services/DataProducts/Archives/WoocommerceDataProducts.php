<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WoocommerceWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CategoryOperations;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use Symfony\Component\DomCrawler\Crawler;

class WoocommerceDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations;

    /** @var WoocommerceWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var string $xmlUrl */
    protected $xmlUrl;

    /** @var array $xmlAgripData */
    private $xmlAgripData;

    /** @var array $xmlEanData */
    private $xmlEanData;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     * @param string $xmlUrl
     */
    public function __construct(string $login, string $password, string $xmlUrl)
    {
        $this->websiteClient = app(WoocommerceWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->xmlUrl =$xmlUrl;
    }

    /**
     * Get
     *
     * @param ProductSource|null $product
     * @return Generator|ProductSource[]
     * @throws DelivererAgripException|GuzzleException
     */
    public function get(?ProductSource $product = null): Generator
    {
        $product = $this->fillProduct($product);
        if ($product) {
            yield $product;
        }
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param string $breadcrumbs
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, string $breadcrumbs): void
    {
        $id = '';
        $mainCategory = null;
        $lastCategory = null;
        foreach (explode('>', $breadcrumbs) as $breadcrumb){
            $breadcrumb = trim($breadcrumb);
            $url = 'https://agrip.pl';
            $name = str_replace('/', '-', $breadcrumb);
            $id = $id ? sprintf('%s_', $id) : $id;
            $id .= Str::slug($name);
            $id = $this->limitReverse($id);
            $category = new CategorySource($id, $name, $url);
            if ($lastCategory) {
                $lastCategory->addChild($category);
            } else {
                $mainCategory = $category;
            }
            $lastCategory = $category;
        }
        if (!$mainCategory) {
            $mainCategory = new CategorySource('pozostale', 'Pozostałe', 'https://agrip.pl');
        }
        $product->setCategories([$mainCategory]);
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $xmlEanData = $this->getXmlEanData($product);
        $manufacturer = $xmlEanData['manufacturer'] ?? '';
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean = $xmlEanData['ean'] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku =$product->getId();
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
//        $weight = $product->getProperty('weight');
//        if ($weight) {
//            $product->addAttribute('Waga', $weight, 350);
//        }
//        $unit = $product->getProperty('unit');
//        if ($unit) {
//            $product->addAttribute('Jednostka', $unit, 500);
//        }
//        $this->addAttributesFromDescriptionProduct($product, $crawlerProduct);
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $addedImages = [];
        $crawlerProduct->filter('.woocommerce-product-gallery__image a')
            ->each(function (Crawler $aElement, $index) use (&$product, &$addedImages) {
                $href = $this->getAttributeCrawler($aElement, 'href');
                if ($href){
                    $main = sizeof($product->getImages()) === 0;
                    $url = sprintf('%s', $href);
                    $filenameUnique = $this->getFilenameUniqueImageProduct($url);
                    $id = $filenameUnique;
                    if (strlen($id) > 50){
                        $id = sprintf('%s_%s', $product->getId(), $index + 1);
                    }
                    if (!in_array($url, $addedImages)) {
                        array_push($addedImages, $url);
                        $product->addImage($main, $id, $url, $filenameUnique);
                    }
                }
            });
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addDescriptionProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $description = '<div class="description">';
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($crawlerProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionWebsiteProduct);
        }
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                $allowName = mb_strtolower($attribute->getName());
                if (!in_array($allowName, ['ean', 'sku'])){
                    $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
                }
            }
            $description .= '</ul></div>';
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get description webapi product
     *
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getDescriptionWebsiteProduct(Crawler $crawlerProduct): string
    {
        $crawlerDescription = $crawlerProduct->filter('#tab-description');
        if (!$crawlerDescription->count()) {
            return '';
        }
//        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
        $crawlerDescription->filter('img')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $crawlerDescription->filter('table')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $crawlerDescription->filter('a')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $descriptionWebsite = trim($crawlerDescription->html());
        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
        if (Str::startsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
        }
        if (Str::endsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceLast('<br>', '', $descriptionWebsite);
        }
        if ($descriptionWebsite) {
//            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
//            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * Get product
     *
     * @param ProductSource $product
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function fillProduct(ProductSource $product): ?ProductSource
    {
        $crawlerProduct = $this->getCrawlerProduct($product);
        $this->addNameProduct($product, $crawlerProduct);
        $breadcrumbs = $this->getBreadcrumbsCategoryProduct($product);
        if (!$breadcrumbs){
            return null;
        }
        $this->addCategoryProduct($product, $breadcrumbs);
        $this->addImagesProduct($product, $crawlerProduct);
        $this->addAttributesProduct($product, $crawlerProduct);
        $this->addDescriptionProduct($product, $crawlerProduct);
        $this->removeSkuFromName($product);
        $product->removeLongAttributes();
        $product->check();
        return $product;
    }

    /**
     * Get crawler product
     *
     * @param ProductSource $product
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getCrawlerProduct(ProductSource $product): Crawler
    {
        $contents = $this->websiteClient->getContents($product->getUrl());
        return $this->getCrawler($contents);
    }

    /**
     * Get data basic
     *
     * @param Crawler $crawlerProduct
     * @param string $name
     * @return string
     */
    private function getDataBasic(Crawler $crawlerProduct, string $name): string
    {
        $value = '';
        $crawlerProduct->filter('table.dane-podstawowe tr')
            ->each(function (Crawler $trElementHtml) use (&$value, $name) {
                $tds = $trElementHtml->filter('td');
                if ($tds->count() === 2) {
                    $nameFound = $this->getTextCrawler($tds->eq(0));
                    if ($nameFound === sprintf('%s:', $name)) {
                        $value = $this->getTextCrawler($tds->eq(1));
                    }
                }
            });
        return $value;
    }

    /**
     * Add data technical attributes product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addAttributesFromDescriptionProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $crawlerProduct->filter('div.item_module_other table.otab tr')
            ->each(function (Crawler $trElement, $index) use (&$product) {
                $tds = $trElement->filter('td');
                if ($tds->count() === 2) {
                    $name = $this->getTextCrawler($tds->eq(0));
                    if (Str::endsWith($name, ':')) {
                        $name = Str::replaceLast(':', '', $name);
                    }
                    if (!in_array(mb_strtolower($name), ['producent', 'symbol'])) {
                        $value = $this->getTextCrawler($tds->eq(1));
                        if ($name && $value && !in_array(mb_strtolower($value), ['brak', 'nie dotyczy', 'nieokreślony'])) {
                            $order = ($index + 10) * 100;
                            $product->addAttribute($name, $value, $order);
                        }

                    }
                }
            });
    }

    /**
     * Get attribute
     *
     * @param string $name
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getAttribute(string $name, Crawler $crawlerProduct): string
    {
        $value = '';
        $crawlerProduct->filter('.product-attributes-ui > ul >li')
            ->each(function (Crawler $attributeElementLi) use (&$name, &$value) {
                $nameAttribute = $this->getTextCrawler($attributeElementLi->filter('.name-ui'));
                if (!$value && mb_strtolower($nameAttribute) === mb_strtolower($name)) {
                    $value = $this->getTextCrawler($attributeElementLi->filter('.value-ui'));
                }
            });
        return $value;
    }

    /**
     * Get category ID product
     *
     * @param string $href
     * @return string
     * @throws DelivererAgripException
     */
    private function getCategoryIdProduct(string $href): string
    {
        $hrefExplode = explode(',', $href);
        if (sizeof($hrefExplode) === 2) {
            throw new DelivererAgripException('Invalid ID category.');
        }
        return $hrefExplode[sizeof($hrefExplode) - 1];
    }

    /**
     * Get filename unique image product
     *
     * @param string $url
     * @return string
     * @throws DelivererAgripException
     */
    private function getFilenameUniqueImageProduct(string $url): string
    {
        if (!Str::contains($url, '/uploads/')) {
            throw new DelivererAgripException('Url image not contains "/uploads/".');
        }
        $explodeUrl = explode('/uploads/', $url)[1];
        return str_replace('/', '-', $explodeUrl);
    }

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addNameProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $name = $this->getTextCrawler($crawlerProduct->filter('h1.product_title'));
        $product->setName($name);
    }

    /**
     * Get data table
     *
     * @param string $name
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getDataTable(string $name, Crawler $crawlerProduct): string
    {
        $value = '';
        $crawlerProduct->filter('div.item_module_other table.otab tr')
            ->each(function (Crawler $trElement) use (&$name, &$value) {
                $tds = $trElement->filter('td');
                if ($tds->count() === 2) {
                    $foundName = $this->getTextCrawler($tds->eq(0));
                    if (Str::endsWith($foundName, ':')) {
                        $foundName = Str::replaceLast(':', '', $foundName);
                    }
                    if (mb_strtolower($name) === mb_strtolower($foundName)) {
                        $value = $this->getTextCrawler($tds->eq(1));
                    }
                }
            });
        return $value;
    }

    /**
     * Remove SKU from name
     *
     * @param ProductSource $product
     */
    private function removeSkuFromName(ProductSource $product): void
    {
        $name = $product->getName();
        $sku = $product->getAttributeValue('SKU');
        $name = str_replace($sku, '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        $product->setName($name);
    }

    /**
     * Get breadcrumbs category product
     *
     * @param ProductSource $product
     * @return string|null
     */
    private function getBreadcrumbsCategoryProduct(ProductSource $product): ?string
    {
        if (!$this->xmlAgripData){
            $this->initializeXmlAgripData();
        }
        return $this->xmlAgripData[$product->getId()]['breadcrumbs'] ?? null;
    }

    /**
     * Initialize XML Agrip data
     */
    private function initializeXmlAgripData(): void
    {
        $cacheKey = sprintf('%s_xml_agrip_data', get_class($this));
        $this->xmlAgripData = Cache::remember($cacheKey, 7200, function (){
            $xmlReader = new XmlFileReader($this->xmlUrl);
            $xmlReader->setTagNameProduct('product');
            $xmlProducts = $xmlReader->read();
            $xmlAgripData = [];
            foreach ($xmlProducts as $xmlProduct){
                $breadcrumbs = $this->getStringXml($xmlProduct->categories->category);
                $sku = $this->getStringXml($xmlProduct->mpn);
                if ($sku && $breadcrumbs){
                    $xmlAgripData[$sku] = [
                        'breadcrumbs' =>$breadcrumbs,
                    ];
                }
            }
            return $xmlAgripData;
        });
    }

    /**
     * Get XML EAN data
     *
     * @param ProductSource $product
     * @return array|null
     */
    private function getXmlEanData(ProductSource $product):?array
    {
        if (!$this->xmlEanData){
            $this->initializeXmlEanData();
        }
        return $this->xmlEanData[$product->getId()]??null;
    }

    /**
     * Initialize XML EAN data
     */
    private function initializeXmlEanData():void
    {
        $url = __DIR__ . '/../../../../../resources/data/eany.xml';
        $cacheKey = sprintf('%s_xml_ean_data_2', get_class($this));
        $this->xmlEanData = Cache::remember($cacheKey, 72000, function () use (&$url){
            $xmlReader = new XmlFileReader($url);
            $xmlReader->setTagNameProduct('offer');
            $xmlProducts = $xmlReader->read();
            $xmlEanData = [];
            foreach ($xmlProducts as $xmlProduct){
                $sku = $this->getStringXml($xmlProduct->id);
                $manufacturer = Str::ucfirst(trim($xmlProduct->xpath('property[@name="Producent"]')[0] ?? ''));
                $manufacturer = $manufacturer === 'Agrip' ? '': $manufacturer;
                $ean = trim($xmlProduct->xpath('property[@name="EAN"]')[0] ?? '');
                $xmlEanData[$sku] = [
                    'manufacturer' =>$manufacturer,
                    'ean' =>$ean,
                ];
            }
            return $xmlEanData;
        });
    }
}
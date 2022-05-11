<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Prestashop2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CategoryOperations;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use Symfony\Component\DomCrawler\Crawler;

class Prestashop2DataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations;

    /** @var Prestashop2WebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * Prestashop2DataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(Prestashop2WebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
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
     * Fill product
     *
     * @param ProductSource $product
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function fillProduct(ProductSource $product): ?ProductSource
    {
        $dataProduct = $this->getDataProduct($product);
        $product->setUrl($dataProduct['product']['url']);
        $this->addTaxProduct($product, $dataProduct);
        $this->addNameProduct($product, $dataProduct);
        $this->addImagesProduct($product, $dataProduct);
        $this->addAttributesProduct($product, $dataProduct);
        $this->addDescriptionProduct($product, $dataProduct);
//        $this->removeSkuFromName($product);
        $product->removeLongAttributes();
        $product->check();
        return $product;
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     */
    private function addAttributesProduct(ProductSource $product, array $dataProduct): void
    {
//        $manufacturer = $xmlEanData['manufacturer'] ?? '';
//        if ($manufacturer) {
//            $product->addAttribute('Producent', $manufacturer, 50);
//        }
        $ean = $dataProduct['product']['ean13'] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku  = $dataProduct['product']['reference'] ?? '';
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
//        $this->addAttributesFromDescriptionProduct($product, $dataProduct);
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $dataProduct): void
    {
        $addedImages = [];
        $images = $dataProduct['product']['images'] ?? [];
        foreach ($images as $image){
            $imageLarge = $image['large'];
            $url = $imageLarge['url'];
            $url = str_replace('-thickbox_default', '', $url);
            $main = sizeof($product->getImages()) === 0;
            $filenameUnique = sprintf('%s.%s', $image['id_image'], $this->extractExtension($url, 'jpg'));
            $id = $filenameUnique;
            if (!in_array($url, $addedImages)) {
                array_push($addedImages, $url);
                $product->addImage($main, $id, $url, $filenameUnique);
            }
        }
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
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($dataProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionWebsiteProduct);
        }
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                $allowName = mb_strtolower($attribute->getName());
                if (!in_array($allowName, ['ean', 'sku'])) {
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
     * @param array $dataProduct
     * @return string
     */
    private function getDescriptionWebsiteProduct(array $dataProduct): string
    {
        $html = $dataProduct['product']['description'];
        if (!$html){
            return '';
        }
        $crawlerDescription = new Crawler();
        $crawlerDescription->addHtmlContent($html);
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
        if (Str::contains($crawlerDescription->html(), '<body>')){
            $descriptionWebsite = trim($crawlerDescription->filter('body')->html());
        } else {
            $descriptionWebsite = trim($crawlerDescription->html());
        }

//        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
//        if (Str::startsWith($descriptionWebsite, '<br>')) {
//            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
//        }
//        if (Str::endsWith($descriptionWebsite, '<br>')) {
//            $descriptionWebsite = Str::replaceLast('<br>', '', $descriptionWebsite);
//        }
//        if ($descriptionWebsite) {
////            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
////            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
//        }
        return $descriptionWebsite;
    }

    /**
     * Get crawler product
     *
     * @param ProductSource $product
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDataProduct(ProductSource $product): array
    {
        $contents = $this->websiteClient->getContentAjax('https://b2b.agrip.pl/index.php?controller=product', [
            RequestOptions::FORM_PARAMS=>[
                'action' => 'quickview',
                'id_product' => $product->getId(),
                'id_product_attribute' => '0',
            ]
        ], 'POST', '{"quickview_html":"<div');
        return json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get data basic
     *
     * @param array $dataProduct
     * @param string $name
     * @return string
     */
    private function getDataBasic(array $dataProduct, string $name): string
    {
        $value = '';
        $dataProduct->filter('table.dane-podstawowe tr')
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
     * @param array $dataProduct
     */
    private function addAttributesFromDescriptionProduct(ProductSource $product, array $dataProduct): void
    {
        $dataProduct->filter('div.item_module_other table.otab tr')
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
     * @param array $dataProduct
     * @return string
     */
    private function getAttribute(string $name, array $dataProduct): string
    {
        $value = '';
        $dataProduct->filter('.product-attributes-ui > ul >li')
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
     * Add tax product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     */
    private function addTaxProduct(ProductSource $product, array $dataProduct): void
    {
        $tax =$dataProduct['product']['rate'];
        $product->setTax($tax);
    }
    
    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param array $dataProduct
     */
    private function addNameProduct(ProductSource $product, array $dataProduct): void
    {
        $name  =Str::limit($dataProduct['product']['name'], 255, '');
        $product->setName($name);
    }

    /**
     * Get data table
     *
     * @param string $name
     * @param array $dataProduct
     * @return string
     */
    private function getDataTable(string $name, array $dataProduct): string
    {
        $value = '';
        $dataProduct->filter('div.item_module_other table.otab tr')
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
        if (!$this->xmlAgripData) {
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
        $this->xmlAgripData = Cache::remember($cacheKey, 7200, function () {
            $xmlReader = new XmlFileReader($this->xmlUrl);
            $xmlReader->setTagNameProduct('product');
            $xmlProducts = $xmlReader->read();
            $xmlAgripData = [];
            foreach ($xmlProducts as $xmlProduct) {
                $breadcrumbs = $this->getStringXml($xmlProduct->categories->category);
                $sku = $this->getStringXml($xmlProduct->mpn);
                if ($sku && $breadcrumbs) {
                    $xmlAgripData[$sku] = [
                        'breadcrumbs' => $breadcrumbs,
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
    private function getXmlEanData(ProductSource $product): ?array
    {
        if (!$this->xmlEanData) {
            $this->initializeXmlEanData();
        }
        return $this->xmlEanData[$product->getId()] ?? null;
    }

    /**
     * Initialize XML EAN data
     */
    private function initializeXmlEanData(): void
    {
        $url = __DIR__ . '/../../../../../resources/data/eany.xml';
        $cacheKey = sprintf('%s_xml_ean_data_2', get_class($this));
        $this->xmlEanData = Cache::remember($cacheKey, 72000, function () use (&$url) {
            $xmlReader = new XmlFileReader($url);
            $xmlReader->setTagNameProduct('offer');
            $xmlProducts = $xmlReader->read();
            $xmlEanData = [];
            foreach ($xmlProducts as $xmlProduct) {
                $sku = $this->getStringXml($xmlProduct->id);
                $manufacturer = Str::ucfirst(trim($xmlProduct->xpath('property[@name="Producent"]')[0] ?? ''));
                $manufacturer = $manufacturer === 'Agrip' ? '' : $manufacturer;
                $ean = trim($xmlProduct->xpath('property[@name="EAN"]')[0] ?? '');
                $xmlEanData[$sku] = [
                    'manufacturer' => $manufacturer,
                    'ean' => $ean,
                ];
            }
            return $xmlEanData;
        });
    }
}
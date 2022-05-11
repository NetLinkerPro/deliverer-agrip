<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\EkspertWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CategoryOperations;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FixJson;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class EkspertDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations, FixJson;

    /** @var EkspertWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var array $xmlData */
    private $xmlData;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(EkspertWebsiteClient::class, [
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
        $this->initXmlData();
        $product = $this->fillProduct($product);
        if ($product) {
            yield $product;
        }
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param array $productXmlData
     */
    private function addCategoryProduct(ProductSource $product, array $productXmlData): void
    {
        $categories = [];
        $breadcrumbs = $productXmlData['category'];
        $explodeBreadcrumbs = explode('-', $breadcrumbs);
        $id = '';
        foreach ($explodeBreadcrumbs as $index => $breadcrumb){
            $name = $breadcrumb;
            $breadcrumb = trim($breadcrumb);
            $breadcrumb = str_replace('-','', Str::slug($breadcrumb));
            $id .= $id ? '_' : '';
            $id .= $breadcrumb;
            if (mb_strlen($id) > 64){
                $id = substr($id, -64, 64);
                DelivererLogger::log(sprintf('Shortened id category %s', $id));
            }
            $url = 'https://sklep.agrip.pl/';
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
        }
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
     * Get HTML breadcrumb
     *
     * @param CategorySource $category
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getHtmlBreadcrumb(CategorySource $category): string
    {
        $key = sprintf('%s_html_getCrawlerBreadcrumb', get_class($this));
        return Cache::remember($key, 3600, function () use (&$category) {
            $contents = $this->websiteClient->getContentAjax('https://www.agrip.pl/index.php', [
                '_log_suffix' => sprintf(' ajax_category %s', $category->getId()),
                RequestOptions::FORM_PARAMS => [
                    'products_action' => 'ajax_category',
                    'is_ajax' => '1',
                    'ajax_type' => 'json',
                    'url' => sprintf('https://www.agrip.pl/offer/pl/0/#/list/?v=t&p=1&gr=%s&s=name&sd=a&st=s', $category->getId()),
                    'locale_ajax_lang' => 'pl',
                    'products_ajax_group' => $category->getId(),
                    'products_ajax_search' => '',
                    'products_ajax_page' => 1,
                    'products_ajax_view' => 't',
                    'products_ajax_stock' => 's',
                    'products_ajax_sort' => 'name',
                    'products_ajax_sort_dir' => 'a',
                    'products_ajax_filter' => '{"srch":""}',
                    'products_ajax_filter_html' => '1',
                    'products_ajax_csv_export' => '0',
                    'products_ajax_use_desc_index' => '1',
                ]
            ]);
            $data = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
            $dataParts = $data[1];
            foreach ($dataParts as $dataPart) {
                if ($dataPart['type'] === 'bc') {
                    $html = $dataPart['data'];
                    $html = str_replace(['<!--', '-->'], '', $html);
                    return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
                }
            }
            throw new DelivererAgripException('Bad response with category breadcrumb.');
        });
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param array $productXmlData
     */
    private function addAttributesProduct(ProductSource $product, array $productXmlData): void
    {
        $manufacturer = $productXmlData['manufacturer'];
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean = $productXmlData['EAN'];
        $ean = explode(',', $ean)[0] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku =$productXmlData['SKU'];
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $unit = $product->getProperty('unit');
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 500);
        }
//        $this->addAttributesFromDataTechnicalTabProduct($product);
//        $this->addAttributesFromDescriptionProduct($product, $crawlerProduct);
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $productXmlData
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $productXmlData): void
    {
        $xmlImages = simplexml_load_string($productXmlData['images'])->xpath('image');
        foreach ($xmlImages as $xmlImage){
            $main = sizeof($product->getImages()) === 0;
            $url = $this->getStringXml($xmlImage);
            $explodeUrl = explode('/', $url);
            $explodeUrl = sprintf('%s-%s', $explodeUrl[sizeof($explodeUrl) -2], $explodeUrl[sizeof($explodeUrl) -1]);
            $filenameUnique = str_replace('/', '-', $explodeUrl);
            $id = $filenameUnique;
            $product->addImage($main, $id, $url, $filenameUnique);
        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param array $productXmlData
     * @throws DelivererAgripException
     */
    private function addDescriptionProduct(ProductSource $product, array $productXmlData): void
    {
        $description = '<div class="description">';
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($productXmlData);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionWebsiteProduct);
        }
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get description webapi product
     *
     * @param array $productXmlData
     * @return string
     * @throws DelivererAgripException
     */
    private function getDescriptionWebsiteProduct(array $productXmlData): string
    {
        $html = $productXmlData['description'];
        return str_replace("\n", "<br/>\n", $html);
//        if (!$crawlerDescription->count()) {
//            return '';
//        }
//        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
//        $crawlerDescription->filter('h3')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
//        $crawlerDescription->filter('img')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
//        $crawlerDescription->filter('table')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
//        $crawlerDescription->filter('a')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
//        $descriptionWebsite = trim($crawlerDescription->html());
//        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
//        if (Str::startsWith($descriptionWebsite, '<br>')) {
//            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
//        }
//        if (Str::endsWith($descriptionWebsite, '<br>')) {
//            $descriptionWebsite = Str::replaceLast('<br>', '', $descriptionWebsite);
//        }
//        if ($descriptionWebsite) {
//            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
//            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
//        }
//        return $descriptionWebsite;
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
        $productXmlData = $this->xmlData[$product->getId()] ?? null;
        DelivererLogger::log(sprintf('Product ID %s.', $product->getId()));
        if (!$productXmlData) {
            DelivererLogger::log('Not found product in XML data.');
            return null;
        }
        $product->setName($productXmlData['name']);
        $this->addTaxProduct($product, $productXmlData);
        $this->addImagesProduct($product, $productXmlData);
        $this->addCategoryProduct($product, $productXmlData);
        $this->addAttributesProduct($product, $productXmlData);
        $this->addDescriptionProduct($product, $productXmlData);
        $product->removeLongAttributes();
        $product->check();
        return $product;
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
        if ($name !== $sku) {
            $name = str_replace($sku, '', $name);
            $name = preg_replace('/\s+/', ' ', $name);
            $name = trim($name);
            $product->setName($name);
        }
    }

    /**
     * Add tax product
     *
     * @param ProductSource $product
     * @param array $productXmlData
     * @throws DelivererAgripException
     */
    private function addTaxProduct(ProductSource $product, array $productXmlData): void
    {
        $textTax = $productXmlData['vat'];
        $tax = $this->extractInteger($textTax);
        if ($tax === null) {
            throw new DelivererAgripException('Not found Tax');
        }
        $product->setTax($tax);
    }

    /**
     * Add price product
     *
     * @param ProductSource $product
     * @param string $pageContents
     */
    private function addPriceProduct(ProductSource $product, string $pageContents): void
    {
        $jsonProduct = $this->getJsonProduct($pageContents);
        $price = (float)$jsonProduct['value'];
        $price = round($price / (1 + ($product->getTax() / 100)), 5);
        $product->setPrice($price);
    }

    /**
     * Get JSON product
     *
     * @param string $pageContents
     * @return array|null
     */
    private function getJsonProduct(string $pageContents): ?array
    {
        $content = explode("fbq('track', 'ViewContent',", $pageContents)[1] ?? '';
        $content = explode(');', $content)[0];
        $content = trim($content);
        return json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
    }

    private function addStockProduct(ProductSource $product, string $pageContents, Crawler $crawlerProduct)
    {
        $jsonProduct = $this->getJsonProduct($pageContents);
        $availability = $this->getAvailabilityProduct($crawlerProduct);
        $stock = 0;
        if ($availability) {
            $contents = $jsonProduct['contents'] ?? '';
            $contents = str_replace("'", '"', $contents);
            $jsonContents = json_decode($contents, true);
            $stock = (int)($jsonContents[0]['quantity'] ?? '');
        }
        $product->setStock($stock);
    }

    /**
     * Get availability product
     *
     * @param Crawler $crawlerProduct
     * @return int
     */
    private function getAvailabilityProduct(Crawler $crawlerProduct): int
    {
        $text = $this->getTextCrawler($crawlerProduct->filter('#projector_status_description'));
        if (Str::contains($text, 'Produkt dostępny')) {
            return 1;
        }
        return 0;
    }

    /**
     * Get ID category product
     *
     * @param string $href
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdCategoryProduct(string $href): string
    {
        $explodeHref = explode('gr=', $href);
        $hrefPart = $explodeHref[sizeof($explodeHref) - 1] ?? '';
        $id = explode('&', $hrefPart)[0];
        $id = (int)$id;
        if (!$id) {
            throw new DelivererAgripException('Not found ID category product.');
        }
        return (string)$id;
    }

    /**
     * Get ID image product
     *
     * @param string $href
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdImageProduct(string $href): string
    {
        $explodeHref = explode('/', $href);
        $href = $explodeHref[sizeof($explodeHref) - 1] ?? '';
        $id = explode('&', $href)[0];
        if (!$id) {
            throw new DelivererAgripException('Not found ID image product.');
        }
        return $id;
    }

    /**
     * Add attributes from data technical tab product
     *
     * @param ProductSource $product
     * @param array $dataTabs
     */
    private function addAttributesFromDataTechnicalTabProduct(ProductSource $product, array $dataTabs): void
    {
        $crawlerTab = $this->getCrawlerTab('prmtable', $dataTabs);
        $crawlerTab->filter('table.prmTab tr')
            ->each(function (Crawler $trElement, $index) use (&$product) {
                $tdElements = $trElement->filter('td');
                if ($tdElements->count() === 2) {
                    $name = $this->getTextCrawler($tdElements->eq(0));
                    $name = Str::replaceLast(':', '', $name);
                    $valueHtml = $tdElements->eq(1)->html();
                    if (Str::contains($valueHtml, '<ul class="ulPrmTab">')) {
                        $value = '';
                        $tdElements->eq(1)->filter('ul > li')
                            ->each(function (Crawler $liElement) use (&$value) {
                                $liValue = $this->getTextCrawler($liElement);
                                if ($liValue) {
                                    $value .= $value ? ', ' : '';
                                    $value .= $liValue;
                                }
                            });
                    } else if (Str::contains($valueHtml, '<br>') || Str::contains($valueHtml, 'class=')) {
                        $value = trim($valueHtml);
                    } else {
                        $value = $this->getTextCrawler($tdElements->eq(1));
                    }
                    $order = ($index * 20) + 1000;
                    if ($name && $value && !in_array($name, ['Kod producenta'])) {
                        $product->addAttribute($name, $value, $order);
                    }
                }
            });
    }

    /**
     * Get crawler product
     *
     * @param ProductSource $product
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerProduct(ProductSource $product): Crawler
    {
        $contents = $this->websiteClient->getContents($product->getUrl());
        return $this->getCrawler($contents);
    }

    /**
     * Get data additional
     *
     * @param string $key
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getDataAdditional(string $key, Crawler $crawlerProduct): string
    {
        $value = '';
        $crawlerProduct->filter('#daneDodatkowe > p')
            ->each(function (Crawler $pElement) use (&$key, &$value) {
                $html = $pElement->html();
                if (Str::contains(mb_strtolower($html), sprintf('%s:', mb_strtolower($key)))) {
                    $partHtml = explode('/b>', $html)[1] ?? '';
                    $value = trim($partHtml);
                }
            });
        return $value;
    }

    /**
     * Get data tabs
     *
     * @param ProductSource $product
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDataTabs(ProductSource $product): array
    {
        $contents = $this->websiteClient->getContentAjax('https://www.agrip.pl/index.php', [
            '_log_suffix' => sprintf(' %s', $product->getId()),
            RequestOptions::FORM_PARAMS => [
                'products_action' => 'ajax_get_tab',
                'is_ajax' => '1',
                'ajax_type' => 'json',
                'url' => sprintf('https://www.agrip.pl/offer/pl/0/#/product/?pr=%s&tab=prmtable&fs=&fi=', $product->getId()),
                'locale_ajax_lang' => 'pl',
                'products_ajax_product' => $product->getId(),
                'products_ajax_product_tab' => 'prmtable',
            ]
        ]);
        $data = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
        $dataParts = $data[1];
        foreach ($dataParts as $dataPart) {
            if ($dataPart['type'] === 'tabdata') {
                return $dataPart['data'];
            }
        }
        throw new DelivererAgripException('Bad response with data tabs.');
    }

    /**
     * Get crawler tab
     *
     * @param string $idTab
     * @param array $dataTabs
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getCrawlerTab(string $idTab, array $dataTabs): Crawler
    {
        foreach ($dataTabs as $dataTab) {
            if ($dataTab[0] === $idTab) {
                $html = $dataTab[1];
                return $this->getCrawler($html);
            }
        }
        throw new DelivererAgripException('Not found tab.');
    }

    /**
     * Get name product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getNameProduct(ProductSource $product, Crawler $crawlerProduct): string
    {
        return $this->getTextCrawler($crawlerProduct->filter('#ContentPlaceHolder1_lblNazwa'));
    }

    /**
     * Initialize XML data
     */
    private function initXmlData(): void
    {
        if (!$this->xmlData){
            $cacheKey = sprintf('%s_xml_data_2', get_class($this));
            $this->xmlData = Cache::remember($cacheKey, 36000, function (){
               $xmlData = [];
                $xmlProducts = $this->getXmlData()->xpath('//produkt');
                foreach ($xmlProducts as $xmlProduct){
                    $id = $this->getStringXml($xmlProduct->ID);
                    $productData = [
                        'id' =>$id,
                        'name' =>$this->getStringXml($xmlProduct->nazwa),
                        'manufacturer' =>$this->getStringXml($xmlProduct->producent),
                        'SKU' =>$this->getStringXml($xmlProduct->kod_producenta),
                        'EAN' =>$this->getStringXml($xmlProduct->EAN),
                        'category' =>$this->getStringXml($xmlProduct->kategoria),
                        'vat' =>$this->getStringXml($xmlProduct->VAT),
                        'description' => $this->getStringXml($xmlProduct->opis),
                        'images' =>$xmlProduct->imageurl->asXML(),
                    ];
                    $xmlData[$id] = $productData;
                }
               return $xmlData;
            });
        }
    }

    /**
     * @return SimpleXMLElement
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getXmlData(): SimpleXMLElement
    {
       $idTree= $this->getIdTree();
       $url = sprintf('https://b2b.agrip.pl/information/file-tree?id=%s', $idTree);
       $contents = $this->websiteClient->getContentAjax($url, [
           RequestOptions::FORM_PARAMS=>[
               'dir' =>'cennik/',
           ],
       ]);
       $crawler = $this->getCrawler($contents);
       $urlXml = sprintf('https://b2b.agrip.pl%s', $this->getAttributeCrawler($crawler->filter('a'), 'rel'));
       $xml = $this->websiteClient->getContents($urlXml, [
           'headers' =>[
               'Referer' =>'https://b2b.agrip.pl/information',
           ]
       ], 2, '<?xml version="1.0"?>');
        return simplexml_load_string($xml);
    }

    /**
     * Get ID tree
     *
     * @return string
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getIdTree(): string
    {
        $contents = $this->websiteClient->getContents('https://b2b.agrip.pl/information');
        $id = explode('/information/file-tree?id=', $contents)[1];
        return explode("',", $id)[0];
    }
}
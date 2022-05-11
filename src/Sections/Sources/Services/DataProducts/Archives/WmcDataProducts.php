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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WmcWebsiteClient;
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

class WmcDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations, FixJson;

    /** @var WmcWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(WmcWebsiteClient::class, [
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
     * Add category product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $crawlerCategory = $this->getTreeCategory($product);
        $categories = [];
        $crawlerCategory->filter('li.jstree-open')
            ->each(function(Crawler $liElement) use (&$product, &$categories){
                $category = $this->parseCategory($liElement);
                array_push($categories, $category);
                $li = clone $liElement;
                foreach (range(1, 10) as $level){
                    $li = $li->parents()->parents();
                    $html = $li->outerHtml();
                    if (Str::startsWith($html, '<li id="')){
                        $category = $this->parseCategory($li);
                        array_push($categories, $category);
                    } else {
                        break;
                    }
                }
                echo "";
            });
        if (!$categories) {
            throw new DelivererAgripException('Not found category product');
        }
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
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $manufacturer = $this->getAttribute('Producent', $crawlerProduct);
        $manufacturer = trim(str_replace('Logistic Sp. z o.o.', '', $manufacturer));
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $brand = $this->getAttribute('Marka', $crawlerProduct);
        $brand = trim(str_replace('Logistic Sp. z o.o.', '', $brand));
        if ($brand && $manufacturer !== $brand) {
            $product->addAttribute('Marka', $brand, 75);
        }
        $ean = $this->getAttribute('EAN', $crawlerProduct);
        $ean = explode(',', $ean)[0] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku = $this->getAttribute('Symbol', $crawlerProduct);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $unit = $product->getProperty('unit');
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 500);
        }
        $this->addAttributesFromTable($product, $crawlerProduct);
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
            $crawlerProduct->filter('.product-big-gallery a')
                ->each(function(Crawler $aElement, $index) use (&$product){
                    $url = $this->getAttributeCrawler($aElement, 'href');
                    $main = sizeof($product->getImages()) === 0;
                    $extension = $this->extractExtension($url, 'png');
                    $filenameUnique = sprintf('%s_%s.%s', $product->getId(), $index+1, $extension);
                    $id = $filenameUnique;
                    $contents = $this->websiteClient->getContents($url, [], 'PNG');
                    $product->addImage($main, $id, $url, $filenameUnique, $extension, null, $contents);
                });
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addDescriptionProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $description = '<div class="description">';
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($crawlerProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionWebsiteProduct);
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
        $crawlerDescription = $crawlerProduct->filter('div.sonata-readmore');
        if (!$crawlerDescription->count()) {
            return '';
        }
        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $crawlerDescription->filter('h3')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
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
            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
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
        DelivererLogger::log(sprintf('Product ID %s.', $product->getId()));
        $this->addTaxProduct($product, $crawlerProduct);
        $this->addImagesProduct($product, $crawlerProduct);
//        $this->addCategoryProduct($product, $crawlerProduct);
        $this->addAttributesProduct($product, $crawlerProduct);
        $this->addDescriptionProduct($product, $crawlerProduct);
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
     * @param Crawler $crawlerProduct
     */
    private function addTaxProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $product->setTax(23);
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
     * Add attributes from table
     *
     * @param ProductSource $product
     * @param array $dataTabs
     * @throws DelivererAgripException
     */
    private function addAttributesFromTable(ProductSource $product, Crawler $crawlerProduct): void
    {
        $crawlerProduct->filter('table.product-show__table-parameters tr')
            ->each(function (Crawler  $trElement, int $index) use (&$product){
                $name = $this->getTextCrawler($trElement->filter('th'));
                $name = trim(Str::replaceFirst(':', '', $name));
                $value = $this->getTextCrawler($trElement->filter('td'));
                if ($name && $value){
                    $order = ($index * 50) + 1000;
                    $product->addAttribute($name, $value, $order);
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

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addNameProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $name = $this->getTextCrawler($crawlerProduct->filter('#productInfo div.nazwa'));
        $product->setName($name);
    }

    /**
     * Get tree category
     *
     * @param ProductSource $product
     * @return Crawler
     */
    private function getTreeCategory(ProductSource $product): Crawler
    {
        $urlProduct = $product->getUrl();
        $idCategory = explode('category/', $urlProduct)[1];
        $idCategory = explode('/', $idCategory)[0];
        $url = sprintf('https://b2b.ama-europe.pl/menuitem/categories-tree/category/%s?id=%%23',$idCategory);
        $keyCache = sprintf('%s_tree_category_%s', get_class($this), $idCategory);
        $contents = Cache::remember($keyCache, 36000, function() use (&$url){
            return $this->websiteClient->getContentAjax($url, [], 'GET', '<ul><li');
        });
        return $this->getCrawler($contents);
    }

    /**
     * Parse category
     *
     * @param Crawler $liElement
     * @return CategorySource
     */
    private function parseCategory(Crawler $liElement): CategorySource
    {
        $id = $this->getAttributeCrawler($liElement, 'id');
        $id = str_replace('k', '', $id);
        $name = $this->getTextCrawler($liElement->filter('a')->eq(0));
        $url = sprintf('https://b2b.ama-europe.pl/offer/index/?search=&field%%5B%%5D=wszystko&quantity=a&category=%s', $id);
        return new CategorySource($id, $name, $url);
    }

    /**
     * Get attribute
     *
     * @param string $key
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getAttribute(string $key, Crawler $crawlerProduct): string
    {
        $value ='';
        $crawlerProduct->filter('div.navbar-header h4')
            ->each(function(Crawler $h4) use (&$key, &$value){
                $textH4 = $this->getTextCrawler($h4);
                if (Str::startsWith($textH4, sprintf('%s:', $key))){
                    $textH4 = str_replace(sprintf('%s:', $key), '', $textH4);
                    $textH4 = trim($textH4);
                    if (!$value && $textH4){
                        $value = $textH4;
                    }
                }
            });
        return $value;
    }
}
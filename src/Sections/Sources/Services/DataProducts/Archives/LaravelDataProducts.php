<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image as InterventionImage;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\LaravelWebsiteClient;
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

class LaravelDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations, FixJson;

    /** @var LaravelWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     * @param string $login2
     */
    public function __construct(string $login, string $password, string $login2)
    {
        $this->websiteClient = app(LaravelWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
            'login2' =>$login2,
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
     * @param array $jsonProduct
     * @throws DelivererAgripException
     */
    private function addAttributesProduct(ProductSource $product, array $jsonProduct): void
    {
//        $manufacturer = $this->getAttribute('Producent', $crawlerProduct);
//        $manufacturer = trim(str_replace('Logistic Sp. z o.o.', '', $manufacturer));
//        if ($manufacturer) {
//            $product->addAttribute('Producent', $manufacturer, 50);
//        }
//        $brand = $this->getAttribute('Marka', $crawlerProduct);
//        $brand = trim(str_replace('Logistic Sp. z o.o.', '', $brand));
//        if ($brand && $manufacturer !== $brand) {
//            $product->addAttribute('Marka', $brand, 75);
//        }
        $ean = $jsonProduct['product']['ean'];
        $ean = explode(',', $ean)[0] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 50);
        }
        $sku = $jsonProduct['product']['code'];
        if ($sku) {
            $product->addAttribute('SKU', $sku, 60);
        }
        $unit = $product->getProperty('unit');
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 70);
        }
        $this->addAttributesFromTable($product, $jsonProduct);
//        $this->addAttributesFromDescriptionProduct($product, $crawlerProduct);
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $jsonProduct): void
    {
           $jsonImages = $jsonProduct['product']['images'] ?? [];
           $images = [];
           foreach ($jsonImages as $jsonImage){
               if (Str::contains(mb_strtolower($jsonImage['filename']), 'dsc_')){
                   array_unshift($images, $jsonImage);
               } else {
                   array_push($images, $jsonImage);
               }
           }
           $addedImages = [];
           $addedSizes = [];
           $addedShortFilenames = [];
           foreach ($images as $image){
               $url = sprintf('https://b2b.agrip.pl/%s', $image['uri']);
               if ($url && !Str::contains($url, 'all_baner') && !in_array($url, $addedImages)){
                   array_push($addedImages, $url);
                   $main = sizeof($product->getImages()) === 0;
                    $shortFilename = mb_strtolower(explode('.', $image['filename'])[0]);
                    if (in_array($shortFilename, $addedShortFilenames)){
                        continue;
                    }
                    if (in_array($image['filename'], ['STAB5.jpg'])){
                        continue;
                    }
                    array_push($addedShortFilenames, $shortFilename);
                   $filenameUnique = sprintf('%s_%s', $product->getId(), $image['filename']);

                   $contents = $this->websiteClient->getContents($url, [], 'JFIF');
                   if (Str::contains($image['filename'], '.')){
                       $extension = $this->extractExtension($image['filename'], 'jpg');
                   } else {
                       $extension = 'jpg';
                   }

                   if (Str::contains($contents, 'PNG')){
                       $extension = 'png';
                   }
                   if (!Str::contains($filenameUnique, '.')){
                       $filenameUnique .= sprintf('.%s', $extension);
                   }
                   $id = $filenameUnique;
                   $imageObj = InterventionImage::make($contents);
                   $size = strlen($contents);
                   if (in_array($size, $addedSizes)){
                       continue;
                   }
                   array_push($addedSizes, $size);
                  $width =  $imageObj->getWidth();
                   $height = $imageObj->getHeight();
                   $ratio = $width / $height;
                   if ($ratio < 8){
                       $product->addImage($main, $id, $url, $filenameUnique, $extension, null, $contents);
                   }
               }
           }
        echo "";
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     */
    private function addDescriptionProduct(ProductSource $product, array $jsonProduct): void
    {
        $description = '<div class="description">';
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                if ($attribute->getOrder() < 200){
                    $description .= sprintf('<li><strong>%s:</strong> %s</li>', $attribute->getName(), $attribute->getValue());
                }
            }
            $description .= '</ul></div>';
        }
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($jsonProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionWebsiteProduct);
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get description webapi product
     *
     * @param array $jsonProduct
     * @return string
     * @throws DelivererAgripException
     */
    private function getDescriptionWebsiteProduct(array $jsonProduct): string
    {
        $description = $jsonProduct['product']['description'] ?? '';
        if (!$description) {
            return '';
        }
        $crawlerDescription = $this->getCrawler($description);
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
        $crawlerDescription->filter('strong')->each(function (Crawler $crawler) {
            $html = $crawler->outerHtml();
            if (Str::contains($html, 'W razie konieczności zastosowania inne')){
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                }
            } else  if (Str::contains($html, 'cena dotyczy 1 metr')){
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                }
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
        if (Str::startsWith($descriptionWebsite, '<body>')){
            $descriptionWebsite = $crawlerDescription->filter('body')->html();
        }
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
     * @throws GuzzleException
     */
    private function fillProduct(ProductSource $product): ?ProductSource
    {
        $jsonProduct = $this->getJsonProduct($product);
        DelivererLogger::log(sprintf('Product ID %s.', $product->getId()));
        $this->addTaxProduct($product, $jsonProduct);
        $this->addImagesProduct($product, $jsonProduct);
//        $this->addCategoryProduct($product, $crawlerProduct);
        $this->addAttributesProduct($product, $jsonProduct);
        $this->addDescriptionProduct($product, $jsonProduct);
        if ($jsonProduct['product']['minimum'] > 1){
            throw new DelivererAgripException('Minimum is greater than 1.');
        }
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
     * @param array $jsonProduct
     */
    private function addTaxProduct(ProductSource $product, array $jsonProduct): void
    {
        $tax = (int) $jsonProduct['product']['vat'];
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

    private function addStockProduct(ProductSource $product, string $pageContents, array $jsonProduct)
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
     * @param array $jsonProduct
     * @return int
     */
    private function getAvailabilityProduct(array $jsonProduct): int
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
     * @param array $jsonProduct
     */
    private function addAttributesFromTable(ProductSource $product, array $jsonProduct): void
    {
        $description = $jsonProduct['product']['description'] ?? '';
        if ($description){
            $crawler = $this->getCrawler($description);
            $crawler->filter('ul li')
                ->each(function (Crawler  $liElement, int $index) use (&$product){
                    $html = $liElement->outerHtml();
                    if ((Str::contains($html, '<b>') || Str::contains($html, '<strong>'))&& Str::contains($html,':')){
                        $text = $this->getTextCrawler($liElement);
                        $explodeText = explode(':', $text, 2);
                        $name = trim($explodeText[0]);
                        $value = trim($explodeText[1]);
                        if ($name && $value){
                            $order = ($index * 50) + 1000;
                            $product->addAttribute($name, $value, $order);
                        }
                    }
                });
        }

    }

    /**
     * Get crawler product
     *
     * @param ProductSource $product
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getJsonProduct(ProductSource $product): array
    {
        $contents = $this->websiteClient->getContentAjax($product->getUrl(), [], 'GET', '{"product":{"id"');
        return json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get data additional
     *
     * @param string $key
     * @param array $jsonProduct
     * @return string
     */
    private function getDataAdditional(string $key, array $jsonProduct): string
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
     * @param array $jsonProduct
     * @return string
     */
    private function getNameProduct(ProductSource $product, array $jsonProduct): string
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
     * @param array $jsonProduct
     */
    private function addNameProduct(ProductSource $product, array $jsonProduct): void
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
     * @param array $jsonProduct
     * @return string
     */
    private function getAttribute(string $key, array $jsonProduct): string
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
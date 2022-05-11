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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Asp2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class Asp2DataProducts implements DataProducts
{
    use CrawlerHtml, ExtensionExtractor, CleanerDescriptionHtml, NumberExtractor;

    /** @var WebsiteClient $websiteClient */
    protected $websiteClient;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(Asp2WebsiteClient::class, [
            'login' => $login,
            'password' => $password,
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
        $jsonProduct = $this->getJsonProduct($product);
        if (!$jsonProduct) {
            DelivererLogger::log(sprintf('Not found JSON product %s.', $product->getId()));
            return false;
        }
        $name = $jsonProduct['details']['model']['displayName'];
        if (!$name) {
            return false;
        }
        $name = Str::limit($name, 255, '');
        if (strlen($name) > 255){
            $name = Str::limit($name, 240, '');
        }
        $product->setName($name);
        $this->addTaxProduct($product, $jsonProduct);
        $this->addCategoryProduct($product, $jsonProduct);
        $this->addImagesProduct($product, $jsonProduct);
        $this->addAttributesProduct($product, $jsonProduct);
        $this->addDescriptionProduct($product, $jsonProduct);
        $product->removeLongAttributes();
        $product->check();
        return true;
    }

    /**
     * Add attribute product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     */
    private function addAttributesProduct(ProductSource $product, array $jsonProduct): void
    {
        $manufacturer = $jsonProduct['details']['model']['brand'];
        $sku = $jsonProduct['details']['model']['itemId'];
        $codeProducer = $jsonProduct['details']['model']['mpn'];
        $oem = $jsonProduct['details']['model']['oem'];
        $ean = $jsonProduct['details']['model']['ean'];
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 10);
        }
        if ($sku) {
            $product->addAttribute('SKU', $sku, 20);
        }
        if ($ean) {
            $product->addAttribute('EAN', $ean, 30);
        }
        if ($codeProducer) {
            $product->addAttribute('Kod producenta', $codeProducer, 37);
        }
        if ($oem) {
            $product->addAttribute('Numer OEM', $oem, 47);
        }
        $specifications = $jsonProduct['details']['model']['specifications'] ?? [];
        $lastOrder = 500;
        foreach ($specifications as $specification) {
            $displayName = mb_strtolower($specification['displayName']);
            if (in_array($displayName, ['vendor information', 'product dimensions', 'packaging data'])) {
                continue;
            }
            $features = $specification['features'];
            foreach ($features as $feature) {
                $name = $feature['displayName'];
                $value = $feature['value'];
                $values = $feature['values'];
                if ($values) {
                    $value = collect($values)->join(', ');
                }
                if ($name && $value) {
                    $unit = $feature['unit'];
                    if ($unit) {
                        $value .= sprintf(' %s', $unit);
                    }
                    $lastOrder += 100;
                    $product->addAttribute($name, $value, $lastOrder);
                }
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
        $explodeUrl = explode('/', $url);
        $id = $explodeUrl[sizeof($explodeUrl) - 1];
        if (!$id || Str::contains($id, ':')) {
            throw new DelivererAgripException('Invalid ID image product');
        }
        return $id;
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
            $description .= '<div class="attributes-section-description" id="description_extra2"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $descriptionWebsiteProduct = $jsonProduct['details']['model']['longSummaryDescription'];
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
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getDescriptionWebsiteProduct(ProductSource $product, Crawler $crawlerProduct): string
    {
        $crawlerDescription = $crawlerProduct->filter('div.description.text-content');
        $crawlerDescription->filter('h1')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $descriptionWebsite = $crawlerDescription->html();
        if ($descriptionWebsite) {
            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * @param ProductSource $product
     * @param array $jsonProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $jsonProduct)
    {
        $images = $jsonProduct['details']['model']['images'] ?? [];
        foreach ($images as $index => $image) {
            $main = $index === 0;
            $url = $image['uri'];
            if ($url) {
                $extension = $image['extension'];
                $url = str_replace('_size_px_', '1400px_', $url);
                $id = $this->getIdImageProduct($url);
                $filenameUnique = $id;
                $product->addImage($main, $id, $url, $filenameUnique, $extension);
            }
        }
    }

    /**
     * Get JSON product
     *
     * @param ProductSource $product
     * @return array|null
     */
    private function getJsonProduct(ProductSource $product): ?array
    {
       try{
           DelivererLogger::log(sprintf('Get data product %s', $product->getId()));
           $url = sprintf('https://api.agrip.com/api/Price/Detailed?itemIds=%s', $product->getId());
           $contentResponse = $this->websiteClient->getContentAjax($url, [], 'GET');
           $jsonPrice = json_decode($contentResponse, true, 512, JSON_UNESCAPED_UNICODE);
           $hasData = $jsonPrice['model']['prices'][0]['currencyCode'] ?? '';
           $widProduct = null;
           if (!$hasData) {
               $idFromUrl = explode('?itemid=', $product->getUrl())[1];
               $widProduct = $idFromUrl;
               $url = sprintf('https://api.agrip.com/api/Price/Detailed?itemIds=%s', $idFromUrl);
               $contentResponse = $this->websiteClient->getContentAjax($url, [], 'GET');
               $jsonPrice = json_decode($contentResponse, true, 512, JSON_UNESCAPED_UNICODE);
               $hasData = $jsonPrice['model']['prices'][0]['currencyCode'] ?? '';
               if (!$hasData) {
                   return null;
               }
           }
           $json['price'] = $jsonPrice;
           if (!$widProduct){
               $widProduct = $this->getWidProduct($product);
           }
           if (!$widProduct){
               return null;
           }
           $url = sprintf('https://api.agrip.com/api/Product/details?productWid=%s&categoryId=', $widProduct);
           $contentResponse = $this->websiteClient->getContentAjax($url, [], 'GET');
           $jsonDetails = json_decode($contentResponse, true, 512, JSON_UNESCAPED_UNICODE);
           $json['details'] = $jsonDetails;
           return $json;
       } catch (Exception | ClientException $e){
           $code = $e->getCode();
           if ($code === 404 || $code === 400){
               return null;
           }
       }
       return null;
    }

    /**
     * Add tax product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     */
    private function addTaxProduct(ProductSource $product, array $jsonProduct): void
    {
        $tax = $jsonProduct['price']['model']['prices'][0]['vatPercent'];
        $tax = str_replace(',', '.', $tax);
        $tax = $this->extractInteger($tax);
        $product->setTax($tax);
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, array $jsonProduct): void
    {
        $categories = [];
        $breadcrumbs = $jsonProduct['details']['model']['breadCrumb'];
        foreach ($breadcrumbs as $breadcrumb) {
            $name = $breadcrumb['name'];
            $id = $breadcrumb['id'];
            $url = $breadcrumb['url'];
            if ($url) {
                $category = new CategorySource($id, $name, $url);
                array_push($categories, $category);
            }
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
     * Get wID product
     *
     * @param ProductSource $product
     * @return string|null
     */
    private function getWidProduct(ProductSource $product): ?string
    {
        $urlSearch = sprintf('https://api.agrip.com/api/Search/Search?term=%s&page=1&pageSize=30&showScore=false&explain=false&useFlattenedCategoryTree=true', $product->getId());
        $contentSearch = $this->websiteClient->getContentAjax($urlSearch, [], 'GET');
        $dataSearch = json_decode($contentSearch, true, 512, JSON_UNESCAPED_UNICODE);
        $products = $dataSearch['model']['products'];
        if (!$products){
            return null;
        }
        foreach ($products as $productSearch){
            $itemId= $productSearch['itemId'];
            if ($itemId === $product->getId()){
                return $productSearch['wid'];
            }
        }
        return null;
    }
}

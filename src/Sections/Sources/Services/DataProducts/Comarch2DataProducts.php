<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Comarch2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use Symfony\Component\DomCrawler\Crawler;

class Comarch2DataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor;

    /** @var Comarch2WebsiteClient $webapiClient */
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
        $this->websiteClient = app(Comarch2WebsiteClient::class, [
            'login' => $login,
            'password' => $password,
            'login2' => $login2,
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
        $mainCategory = null;
        /** @var CategorySource $lastCategory */
        $lastCategory = null;
        $crawlerProduct->filter('ul.breadcrumbs-ui > li >a')
            ->each(function(Crawler $elementA) use (&$mainCategory, &$lastCategory){
                $href = $this->getAttributeCrawler($elementA, 'href');
               $comas = substr_count($href, ',');
                if ($href && $comas > 1){
                    $id = $this->getCategoryIdProduct($href);
                    $name = str_replace('/', '-', $this->getTextCrawler($elementA));
                    $url = sprintf('https://agrip.pl/%s', $href);
                    $category = new CategorySource($id, $name, $url);
                    if ($lastCategory){
                        $lastCategory->addChild($category);
                    } else {
                        $mainCategory = $category;
                    }
                    $lastCategory = $category;
                }
            });
        if (!$mainCategory) {
            $mainCategory = new CategorySource('pozostale', 'Pozostałe', 'https://agrip.pl');
        }
        $product->setCategories([$mainCategory]);
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     * @param array $jsonAttributes
     */
    private function addAttributesProduct(ProductSource $product, array $jsonProduct, array $jsonAttributes): void
    {
        $manufacturer = trim($jsonProduct['productDetails']['manufacturer'] ?? '');
        if (!$manufacturer){
            $manufacturer = trim($jsonProduct['productDetails']['brand'] ?? '');
        }
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean =($jsonProduct['productDetails']['ean'] ?? '');
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku =($jsonProduct['productDetails']['code'] ?? '');
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $weight = (float) ($jsonProduct['productDetails']['bruttoWeight'] ?? '');
        if ($weight) {
            $weight = str_replace('.', ',', $weight);
            $weight .= ' kg';
            $product->addAttribute('Waga', $weight, 350);
        }
        $unit = $product->getProperty('unit');
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 500);
        }
        $features = $jsonAttributes['items']['set1'] ?? [];
        foreach ($features as $index => $feature) {
            $name = $feature['name'];
            $value = trim($feature['value']);
            if ($name && $value && !Str::contains($name, 'Kategoria dostawy')){
                $order = 1000 + ($index * 100);
                $product->addAttribute($name, $value, $order);
            }
        }
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $jsonAttributes
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $jsonAttributes): void
    {

        $images = $jsonAttributes['set3']??$jsonAttributes['items']['set3']??[];
        foreach ($images as $image){
            $main = sizeof($product->getImages()) === 0;
            $url = sprintf('http://www.b2b.agrip.info/imagehandler.ashx?id=%s&frombinary=&width=2048&height=2048',$image['id']);
            $filenameUnique = sprintf('%s.jpg', $image['id']);
            $id = $filenameUnique;
            $product->addImage($main, $id, $url, $filenameUnique, 'jpg', null, $this->websiteClient->getContents($url, [], false));
        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     * @throws DelivererAgripException
     */
    private function addDescriptionProduct(ProductSource $product, array $jsonProduct): void
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
        $descriptionHtml = trim($jsonProduct['productDetails']['description'] ?? '');
        if (!$descriptionHtml){
            return '';
        }
        $crawlerDescription = new Crawler();
        $crawlerDescription->addHtmlContent($descriptionHtml);
//        $crawlerDescription = $crawlerProduct->filter('div.product-description-ui');
//        if (!$crawlerDescription->count()){
//            return '';
//        }
        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
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
        $crawlerDescription->filter('iframe')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        if ($crawlerDescription->filter('body')->count()){
            $descriptionWebsite = trim($crawlerDescription->filter('body')->html());
        } else {
            $descriptionWebsite = trim($crawlerDescription->html());
        }

        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
        $descriptionWebsite = str_replace(['<center></center>'], '', $descriptionWebsite);
//        if (Str::startsWith($descriptionWebsite, '<br>')) {
//            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
//        }
//        if ($descriptionWebsite) {
//            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
//            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
//        }
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
        $jsonProduct = $this->getJsonProduct($product);
        $this->addNameProduct($product, $jsonProduct);
        $jsonAttributes = $this->getJsonAttributes($product);
        $this->addImagesProduct($product, $jsonAttributes);
        $this->addAttributesProduct($product, $jsonProduct, $jsonAttributes);
        $this->addDescriptionProduct($product, $jsonProduct);
        $product->removeLongAttributes();
        $product->check();
        return $product;
    }

    /**
     * Get JSON product
     *
     * @param ProductSource $product
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getJsonProduct(ProductSource $product): array
    {
        $url = sprintf('http://www.b2b.agrip.info/api/items/%s?warehouseId=1&contextGroupId=null', $product->getId());
        $contents = $this->websiteClient->getContentAjax($url, [],'GET', '{"productDetails":{');
        return json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get product tax
     *
     * @param Crawler $crawlerProduct
     * @return int
     */
    private function getProductTax(Crawler $crawlerProduct): int
    {
        $textTax = $this->getAttribute('Podatek VAT', $crawlerProduct);
        $textTax = str_replace('%', '', $textTax);
        if (!$textTax) {
            $tax = 23;
        } else {
            $tax = (int)$textTax;
        }
        return $tax;
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
        $crawlerProduct->filter('div.product-description-ui table tr')
            ->each(function (Crawler $trElement, $index) use (&$product) {
                $tds = $trElement->filter('td');
                if ($tds->count() === 2){
                    $name = $this->getTextCrawler($tds->eq(0));
                    if (Str::endsWith($name, ':')){
                        $name = Str::replaceLast(':', '', $name);
                    }
                    $value = $this->getTextCrawler($tds->eq(1));
                    if ($name && $value){
                        $order = ($index + 10) * 100;
                        $product->addAttribute($name, $value, $order);
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
        if (sizeof($hrefExplode) ===2){
            throw new DelivererAgripException('Invalid ID category.');
        }
        return $hrefExplode[sizeof($hrefExplode) -1];
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
        if (!Str::contains($url, '/img/')){
            throw new DelivererAgripException('Url image not contains "/img".');
        }
        $explodeUrl = explode('/img/', $url)[1];
        return str_replace('/', '-', $explodeUrl);
    }

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param array $jsonProduct
     */
    private function addNameProduct(ProductSource $product, array $jsonProduct): void
    {
        $name = $jsonProduct['productDetails']['name'];
        $code = $jsonProduct['productDetails']['code'];
        $name = str_replace($code, '', $name);
        $name = trim($name);
        if (!$name){
            $name = $code;
        }
        $product->setName($name);
    }

    /**
     * Get JSON attributes
     *
     * @param ProductSource $product
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getJsonAttributes(ProductSource $product): array
    {
        $url = sprintf('http://www.b2b.agrip.info/api/items/attributes/%s', $product->getId());
        $contents = $this->websiteClient->getContentAjax($url, [], 'GET', '{"set3":[');
        return json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\ComarchWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use Symfony\Component\DomCrawler\Crawler;

class ComarchDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor;

    /** @var ComarchWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(ComarchWebsiteClient::class, [
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
     * @param Crawler $crawlerProduct
     */
    private function addAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $manufacturer = $product->getProperty('manufacturer');
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean = $this->getAttribute('Kod EAN', $crawlerProduct);
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku = $product->getProperty('SKU');
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $weight = $product->getProperty('weight');
        if ($weight) {
            $product->addAttribute('Waga', $weight, 350);
        }
        $unit = $product->getProperty('unit');
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 500);
        }
        $this->addAttributesFromDescriptionProduct($product, $crawlerProduct);
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
        $crawlerProduct->filter('div.single-image-slider-ui img.open-gallery-lq')
            ->each(function (Crawler $imgElement) use (&$product) {
                $main = sizeof($product->getImages()) === 0;
                $url = sprintf('https:%s',$this->getAttributeCrawler($imgElement, 'data-lazy'));
                $filenameUnique = $this->getFilenameUniqueImageProduct($url);
                $id = $filenameUnique;
                $product->addImage($main, $id, $url, $filenameUnique);
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
        $crawlerDescription = $crawlerProduct->filter('div.product-description-ui');
        if (!$crawlerDescription->count()){
            return '';
        }
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
        $descriptionWebsite = trim($crawlerDescription->html());
        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
        if (Str::startsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
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
        $product->setTax($this->getProductTax($crawlerProduct));
       // $this->addCategoryProduct($product, $crawlerProduct);
        $this->addImagesProduct($product, $crawlerProduct);
        $this->addAttributesProduct($product, $crawlerProduct);
        $this->addDescriptionProduct($product, $crawlerProduct);
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
        $contents = $this->websiteClient->getContent($product->getUrl());
        return $this->getCrawler($contents);
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
}
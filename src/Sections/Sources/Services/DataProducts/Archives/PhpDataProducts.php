<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CategoryOperations;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use Symfony\Component\DomCrawler\Crawler;

class PhpDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations;

    /** @var PhpWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(PhpWebsiteClient::class, [
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
        $breadCrumbs = $crawlerProduct->filter('div.breadcrumb ul > li >a');
        $sizeBreadcrumbs = $breadCrumbs->count();
        $id = '';
        $mainCategory = null;
        /** @var CategorySource $lastCategory */
        $lastCategory = null;
        $breadCrumbs->each(function (Crawler $elementA, $index) use (&$mainCategory, &$lastCategory, $sizeBreadcrumbs, &$id) {
            if ($index && $index < $sizeBreadcrumbs - 1) {
                $href = $this->getAttributeCrawler($elementA, 'href');
                $name = str_replace('/', '-', $this->getTextCrawler($elementA));
                $id = $id ? sprintf('%s_', $id) : $id;
                $id .= Str::slug($name);
                $id = $this->limitReverse($id);
                $url = sprintf('https://www.agrip.pl%s', $href);
                $category = new CategorySource($id, $name, $url);
                if ($lastCategory) {
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
        $manufacturer = $this->getDataTable('Producent', $crawlerProduct);
        if (mb_strtolower($manufacturer) === 'Nieokreślony') {
            $manufacturer = '';
        }
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
//        $ean = $this->getAttribute('Kod EAN', $crawlerProduct);
//        if ($ean) {
//            $product->addAttribute('EAN', $ean, 100);
//        }
        $sku = $this->getDataTable('Symbol', $crawlerProduct);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
//        $weight = $product->getProperty('weight');
//        if ($weight) {
//            $product->addAttribute('Waga', $weight, 350);
//        }
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
        $addedImages = [];
        $crawlerProduct->filter('div.item_module_img a.item_img_link')
            ->each(function (Crawler $aElement) use (&$product, &$addedImages) {
                $href = $this->getAttributeCrawler($aElement, 'href');
                if (Str::contains($href, '/img/offer/')) {
                    $main = sizeof($product->getImages()) === 0;
                    $url = sprintf('https://www.agrip.pl%s', $href);
                    $filenameUnique = $this->getFilenameUniqueImageProduct($url);
                    $id = $filenameUnique;
                    if (!in_array($url, $addedImages)) {
                        array_push($addedImages, $url);
                        $product->addImage($main, $id, $url, $filenameUnique);
                    }
                }
            });
        $crawlerProduct->filter('div.item_module_img a[rel="imgr"]')
            ->each(function (Crawler $aElement) use (&$product, &$addedImages) {
                $href = $this->getAttributeCrawler($aElement, 'href');
                if (Str::contains($href, '/img/offer/')) {
                    $main = sizeof($product->getImages()) === 0;
                    $url = sprintf('https://www.agrip.pl%s', $href);
                    $filenameUnique = $this->getFilenameUniqueImageProduct($url);
                    $id = $filenameUnique;
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
        $crawlerDescription = $crawlerProduct->filter('div.item_module_info table.otab td');
        if (!$crawlerDescription->count()) {
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
        $this->addNameProduct($product, $crawlerProduct);
        $this->addCategoryProduct($product, $crawlerProduct);
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
        $contents = $this->websiteClient->getContent($product->getUrl());
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
        if (!Str::contains($url, '/img/offer/')) {
            throw new DelivererAgripException('Url image not contains "/img/offer/".');
        }
        $explodeUrl = explode('/img/offer/', $url)[1];
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
//        $name = $this->getTextCrawler($crawlerProduct->filter('#right_column h1.title'));
//        $name = str_replace(';', '', $name);
//        $minimumQuantity = $product->getProperty('minimum_quantity');
//        if ($minimumQuantity > 1) {
//            $name = sprintf('%s /%s%s', $name, $minimumQuantity, $product->getProperty('unit'));
//        }
        $product->setName($product->getProperty('long_name'));
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
}
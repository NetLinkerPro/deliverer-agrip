<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\CsvFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\CorpsListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\CorpsWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class CorpsDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor;

    /** @var CorpsWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var ListCategories $listCategories */
    protected $listCategories;

    /** @var array $categories */
    private $categories;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(CorpsWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(CorpsListCategories::class, [
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
        $categories = $this->getCategories();
        $idCategory = '';
        $crawlerProduct->filter('ol.breadcrumb a')
            ->each(function (Crawler $aElementHtml) use (&$idCategory) {
                $href = $this->getAttributeCrawler($aElementHtml, 'href');
                if ($href) {
                    $foundIdCategory = explode('produkty-do-kategorii/', $href)[1] ?? '';
                    if ($foundIdCategory) {
                        $idCategory = $foundIdCategory;
                    }
                }
            });
        if (!$idCategory) {
            $category = new CategorySource('pozostale', 'Pozostałe','https://b2b.agrip.pl');
        } else {
            $category = $categories[$idCategory];
        }
        $product->setCategories([$category]);
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $manufacturer = $this->getDataBasic($crawlerProduct, 'Producent');
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean = $this->getDataBasic($crawlerProduct, 'EAN');
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $product->addAttribute('SKU', $product->getId(), 200);
        $weight = $this->getDataBasic($crawlerProduct, 'Waga');
        if ($weight) {
            $product->addAttribute('Waga', $weight, 350);
        }
        $unit = $this->getDataBasic($crawlerProduct, 'Jednostka');
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 500);
        }
        $this->addDataTechnicalAttributesProduct($product, $crawlerProduct);
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addImagesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $crawlerProduct->filter('#gallery a')
            ->each(function (Crawler $aElementHtml) use (&$product) {
                $href = $url = $this->getAttributeCrawler($aElementHtml, 'href');
                $url = sprintf('https://b2b.agrip.pl%s', $href);
                $hrefText = explode('zdjecie-produktu/', $href)[1] ?? '';
                $explodeHref = explode('/', $hrefText);
                if (sizeof($explodeHref) === 2) {
                    $number = $explodeHref[0];
                    $identifierProduct = $explodeHref[1];
                    if ($number && $identifierProduct) {
                        $main = sizeof($product->getImages()) === 0;
                        $filenameUnique = sprintf('%s-%s.jpg', $identifierProduct, $number);
                        $id = $filenameUnique;
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
            $description .= '<div class="attributes-section-description" id="description_extra2"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($crawlerProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra3">%s</div>', $descriptionWebsiteProduct);
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
        $crawlerDescription = $crawlerProduct->filter('#opis');
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
        if (Str::startsWith($descriptionWebsite, '<br>')){
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
        $name = $this->getTextCrawler($crawlerProduct->filter('#whole-content-wrapper h1'));
        if (!$name) {
            return null;
        }
        $product->setName($name);
        $product->setTax($this->getProductTax($crawlerProduct));
        $product->setAvailability(1);
        $this->addCategoryProduct($product, $crawlerProduct);
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
        $nettoPrice = $this->getProductNettoPrice($crawlerProduct);
        $bruttoPrice = $this->getProductBruttoPrice($crawlerProduct);
        return intval(round((($bruttoPrice / $nettoPrice) - 1) * 100));
    }

    /**
     * Get product netto price
     *
     * @param Crawler $crawlerProduct
     * @return float
     */
    private function getProductNettoPrice(Crawler $crawlerProduct): float
    {
        $nettoPriceText = $this->getDataBasic($crawlerProduct, 'Cena netto');
        $nettoPriceText = explode('PLN', $nettoPriceText)[0];
        $nettoPriceText = str_replace([',', ' ', 'PLN'], ['.', '', ''], $nettoPriceText);
        return $this->extractFloat($nettoPriceText);
    }

    /**
     * Get product brutto price
     *
     * @param Crawler $crawlerProduct
     * @return float
     */
    private function getProductBruttoPrice(Crawler $crawlerProduct): float
    {
        $nettoPriceText = $this->getDataBasic($crawlerProduct, 'Cena brutto');
        $nettoPriceText = str_replace([',', ' ', 'PLN'], ['.', '', ''], $nettoPriceText);
        return $this->extractFloat($nettoPriceText);
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
     * Get categories
     *
     * @return array
     */
    private function getCategories(): array
    {
        if (!$this->categories) {
            $categories = iterator_to_array($this->listCategories->get());
            /** @var CategorySource $category */
            foreach ($categories as $categoryRoot) {
                $category = $categoryRoot;
                $idDepthCategory = $category->getId();
                while ($category) {
                    $category = $category->getChildren()[0] ?? null;
                    if ($category) {
                        $idDepthCategory = $category->getId();
                    }
                }
                $this->categories[$idDepthCategory] = $categoryRoot;
            }
        }
        return $this->categories;
    }

    /**
     * Add data technical attributes product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addDataTechnicalAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $crawlerProduct->filter('#dane-techniczne div.grupa-specyfikacji > div.row')
            ->each(function (Crawler $divElementHtml, $index) use (&$product) {
                $name = $this->getTextCrawler($divElementHtml->filter('.tytul'));
                $name = str_replace([':'], [''], $name);
                $value = $this->getTextCrawler($divElementHtml->filter('.wartosc'));
                if ($name && $value && !in_array($name, ['Waga'])){
                    $order = ($index * 100) + 600;
                    $product->addAttribute($name, $value, $order);
                }
            });
    }
}
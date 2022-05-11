<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\IdosellWebsiteClient;
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

class IdosellDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations;

    /** @var IdosellWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(IdosellWebsiteClient::class, [
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
        $breadCrumbs = $crawlerProduct->filter('div.breadcrumbs ol > li > a');
        $mainCategory = null;
        /** @var CategorySource $lastCategory */
        $lastCategory = null;
        $breadCrumbs->each(function (Crawler $elementA) use (&$mainCategory, &$lastCategory) {
            $href = $this->getAttributeCrawler($elementA, 'href');
            $name = str_replace('/', '-', $this->getTextCrawler($elementA));
            $id = $this->getIdCategoryProduct($href);
            $url = sprintf('https://agrip.pl/pol_m_-%s.html', $id);
            $category = new CategorySource($id, $name, $url);
            if ($lastCategory) {
                $lastCategory->addChild($category);
            } else {
                $mainCategory = $category;
            }
            $lastCategory = $category;
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
        $manufacturer = $this->getTextCrawler($crawlerProduct->filter('.basic_info .producer .brand'));
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $series = $this->getTextCrawler($crawlerProduct->filter('.basic_info .series a'));
        if ($series) {
            $product->addAttribute('Seria', $series, 75);
        }
        $ean = $this->getTextCrawler($crawlerProduct->filter('#producert_code_number'));
        $ean = explode('/', $ean)[0] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku = $this->getTextCrawler($crawlerProduct->filter('.basic_info .code strong'));
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $unit = $this->getTextCrawler($crawlerProduct->filter('#projector_price_unit'));
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 500);
        }
        $this->addAttributesFromDataTechnicalTabProduct($product, $crawlerProduct);
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
        $crawlerProduct->filter('a.projector_medium_image')
            ->each(function (Crawler $aElement) use (&$product, &$addedImages) {
                $href = $this->getAttributeCrawler($aElement, 'href');
                $main = sizeof($product->getImages()) === 0;
                $filenameUnique = $this->getUniqueFilenameImageProduct($href);
                $url = sprintf('https://agrip.pl/pol_pl_-%s', $filenameUnique);
                $id = $filenameUnique;
                if (!in_array($url, $addedImages)) {
                    array_push($addedImages, $url);
                    $product->addImage($main, $id, $url, $filenameUnique);
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
        $crawlerDescription = $crawlerProduct->filter('#component_projector_longdescription');
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
        $pageContents = $this->websiteClient->getContent($product->getUrl());
        $crawlerProduct = $this->getCrawler($pageContents);
        $product->setAvailability(1);
        $this->addTaxProduct($product, $crawlerProduct);
        $this->addPriceProduct($product, $pageContents);
        $this->addStockProduct($product, $pageContents, $crawlerProduct);
        $this->addNameProduct($product, $crawlerProduct);
        if (!$product->getName()){
            return null;
        }
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
     * Add name product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addNameProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $name = $this->getTextCrawler($crawlerProduct->filter('.projector_navigation .product_intro h1'));
        $product->setName($name);
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
        if ($name !== $sku){
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
     * @throws DelivererAgripException
     */
    private function addTaxProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $textTax = $this->getTextCrawler($crawlerProduct->filter('span.vat_info'));
        $tax = $this->extractInteger($textTax);
        if (!$tax) {
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
        $explodeHref = explode('-', $href);
        $hrefPart = $explodeHref[sizeof($explodeHref)-1] ?? '';
        $id = str_replace('.html', '', $hrefPart);
        $id = (int) $id;
        if (!$id){
            throw new DelivererAgripException('Not found ID category product.');
        }
        return (string) $id;
    }

    /**
     * Get unique filename image product
     *
     * @param string $href
     * @return string
     * @throws DelivererAgripException
     */
    private function getUniqueFilenameImageProduct(string $href): string
    {
        $explodeHref = explode('-', $href);
        $uniqueFilename =  $explodeHref[sizeof($explodeHref)-1] ?? '';
        if (!$uniqueFilename){
            throw new DelivererAgripException('Not found unique filename image product.');
        }
        return $uniqueFilename;
    }

    /**
     * Add attributes from data technical tab product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addAttributesFromDataTechnicalTabProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $crawlerProduct->filter('#component_projector_dictionary_no table tr')
            ->each(function(Crawler $trElement, $index) use(&$product){
               $tdElements = $trElement->filter('td');
               if ($tdElements->count() === 2){
                   $name = $this->getTextCrawler($tdElements->eq(0));
                   $name = Str::replaceLast(':', '', $name);
                   $value = $this->getTextCrawler($tdElements->eq(1));
                   $order = ($index * 20) + 1000;
                   if ($name && $value && !in_array($name, ['Kod producenta'])){
                       $product->addAttribute($name, $value, $order);
                   }
               }
            });
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AspWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SymfonyWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use Symfony\Component\DomCrawler\Crawler;

class SymfonyDataProducts implements DataProducts
{
    use CrawlerHtml, ExtensionExtractor, CleanerDescriptionHtml;

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
        $this->websiteClient = app(SymfonyWebsiteClient::class, [
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
        $crawlerProduct = $this->getCrawlerProduct($product);
        $this->addManufacturerPropertyProduct($product, $crawlerProduct);
        $this->addEanPropertyProduct($product, $crawlerProduct);
        $this->addSkuPropertyProduct($product, $crawlerProduct);
        $this->addImagesProduct($product, $crawlerProduct);
        $this->addAttributesProduct($product, $crawlerProduct);
        $this->addDescriptionProduct($product, $crawlerProduct);
        $this->removeLongAttributes($product);
        $product->check();
        return true;
    }

    /**
     * Add attribute product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $manufacturer = $product->getProperty('manufacturer');
        $sku = $product->getProperty('sku');
        $ean = $product->getProperty('ean');
        $unit = $product->getProperty('unit');
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 15);
        }
        if ($sku) {
            $product->addAttribute('SKU', $sku, 20);
        }
        if ($ean) {
            $product->addAttribute('EAN', $ean, 30);
        }
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 40);
        }
        $crawlerProduct->filter('#tab_card1 table tr')
            ->each(function(Crawler $trHtmlElement, $index) use (&$product){
                $tds = $trHtmlElement->filter('td');
                if ($tds->count() === 2){
                    $name = $this->getTextCrawler($tds->eq(0));
                    $value = $this->getTextCrawler($tds->eq(1));
                    if ($name && $value){
                        $product->addAttribute($name, $value, ($index + 1) * 100);
                    }
                }
            });
    }

    /**
     * Get ID image product
     *
     * @param string $url
     * @return string|null
     */
    private function getIdImageProduct(string $url): ?string
    {
        $explodeUrl = explode('/hash/', $url);
        $explodeUrl = explode('/', $explodeUrl[1] ?? '');
        $id = $explodeUrl[0] ?? '';
        if (!$id || Str::contains($id, ':')) {
            return null;
        }
        return $id;
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
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($product, $crawlerProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra3">%s</div>', $descriptionWebsiteProduct);
        }
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra2"><ul>';
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
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getDescriptionWebsiteProduct(ProductSource $product, Crawler $crawlerProduct): string
    {
        $crawlerDescription = $crawlerProduct->filter('#tab_content');
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
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, Crawler $crawlerProduct)
    {
        $crawlerProduct->filter('#attach-content a')->each(function (Crawler $imgHtmlElement, $index) use (&$product) {
            $main = $index === 0;
            $url = sprintf('https://agrip.de%s', $this->getAttributeCrawler($imgHtmlElement, 'href'));
            $id = $this->getIdImageProduct($url);
            if ($id){
                $filenameUnique = sprintf('%s.jpg', $id);
                $product->addImage($main, $id, $url, $filenameUnique);
            }
        });
    }

    /**
     * Get crawler product
     *
     * @param ProductSource $product
     * @return Crawler
     */
    private function getCrawlerProduct(ProductSource $product): Crawler
    {
        DelivererLogger::log(sprintf('Get data product %s', $product->getId()));
        $contentResponse = $this->websiteClient->getContents($product->getUrl());
        return $this->getCrawler($contentResponse);
    }

    /**
     * Add manufacturer property product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addManufacturerPropertyProduct(ProductSource $product, Crawler $crawlerProduct)
    {
        $manufacturer = $this->getTextCrawler($crawlerProduct->filter('.extends .producer'));
        if (!$manufacturer){
            $manufacturer =  $this->getAttributeCrawler($crawlerProduct->filter('.extends .producer img'), 'alt');
        }
       if ($manufacturer){
           $product->setProperty('manufacturer', $manufacturer);
       }
    }

    /**
     * Add EAN property product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addEanPropertyProduct(ProductSource $product, Crawler $crawlerProduct)
    {
        $ean = $this->getTextDlElement('Kod kreskowy:', $crawlerProduct);
        if ($ean){
            $product->setProperty('ean', $ean);
        }
    }

    /**
     * Add SKU property product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addSkuPropertyProduct(ProductSource $product, Crawler $crawlerProduct)
    {
        $sku = $this->getTextDlElement('Indeks katalogowy:', $crawlerProduct);
        $product->setProperty('sku', $sku);
    }

    /**
     * Get text dl element
     *
     * @param string $name
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getTextDlElement(string $name, Crawler $crawlerProduct): string
    {
        $textDlElement = '';
        $textDtElementLast = '';
        $crawlerProduct->filter('div.extends dl.dl-horizontal > *')
            ->each(function (Crawler $htmlElement) use (&$name, &$textDtElementLast, &$textDlElement) {
                $textHtmlElement = $this->getTextCrawler($htmlElement);
                if ($textDtElementLast === $name && !$textDlElement){
                    $textDlElement = $this->getTextCrawler($htmlElement);
                }
                if ($htmlElement->nodeName() === 'dt'){
                    $textDtElementLast = $textHtmlElement;
                }
            });
        return $textDlElement;
    }

    /**
     * Remove long attributes
     *
     * @param ProductSource $product
     */
    private function removeLongAttributes(ProductSource $product): void
    {
        $attributes = $product->getAttributes();
        foreach ($attributes as $index => $attribute){
            if (mb_strlen($attribute->getName()) > 50){
                unset($attributes[$index]);
            }
        }
        $product->setAttributes($attributes);
    }
}
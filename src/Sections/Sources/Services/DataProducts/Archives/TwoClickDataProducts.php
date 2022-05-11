<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;


use Generator;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Magento2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\TwoClickWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\HtmlDecimalUnicodeDecoder;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class TwoClickDataProducts implements DataProducts
{

    use CrawlerHtml, CleanerDescriptionHtml, NumberExtractor, ExtensionExtractor, XmlExtractor, HtmlDecimalUnicodeDecoder;

    /** @var WebsiteClient $websiteClient */
    private $websiteClient;

    /**
     * Magento2ListCategories constructor
     */
    public function __construct()
    {
        $this->websiteClient = app(TwoClickWebsiteClient::class);
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
        $xmlProduct = $product->getProperty('xml_product');
        $this->addAttributesProduct($product, $xmlProduct);
        $this->addDescriptionProduct($product, $xmlProduct);
        $product->removeLongAttributes();
        $product->check();
        yield $product;
    }


    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     */
    private function addAttributesProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $manufacturer = $this->getStringXml($xmlProduct->producer);
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 10);
        }
        $sku = $this->getStringXml($xmlProduct->symbol);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 20);
        }
        $ean = $this->getStringXml($xmlProduct->ean);
        if ($ean) {
            $product->addAttribute('EAN', $ean, 30);
        }
        $urlProduct = $this->findUrlProduct($sku);
        if ($urlProduct){
            $crawlerPage = $this->getCrawlerPage($urlProduct);
            $crawlerPage->filter('.productDetails__item--params > div > div.productDetails__wrap > div.productParams > .row_up')
                ->each(function (Crawler $div, $index) use (&$product){
                   $name = $this->getTextCrawler($div->filter('.productParams__name'));
                    $name = Str::ucfirst(mb_strtolower($name));
                   $value =  $this->getTextCrawler($div->filter('.productParams__param'));
                   if ($name && $value){
                       $order = ($index * 50) + 120;
                       $product->addAttribute($name, $value, $order);
                   }
                });
        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     * @throws DelivererAgripException
     */
    private function addDescriptionProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $description = '<div class="description">';
        $descriptionProduct = $this->getStringXml($xmlProduct->desc);
        if ($descriptionProduct) {
            $descriptionProduct = $this->correctDescriptionProduct($descriptionProduct);
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionProduct);
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
     * Correct description product
     *
     * @param string $html
     * @return string
     * @throws DelivererAgripException
     */
    private function correctDescriptionProduct(string $html): string
    {
        $html = $this->decodeToUtf8($html);
        $crawlerDescription = $this->getCrawler($html);
        if (!$crawlerDescription->count()){
            return '';
        }
//        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
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

        $descriptionWebsite = trim($crawlerDescription->filter('body')->html());
        $descriptionWebsite = preg_replace('/<a[^>]+\>/i', '', $descriptionWebsite);
        $descriptionWebsite =preg_replace('/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', '', $descriptionWebsite);

//        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
//        if (Str::startsWith($descriptionWebsite, '<br>')) {
//            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
//        }
        if ($descriptionWebsite) {
            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * Find URL product
     *
     * @param string $sku
     * @return string|null
     */
    private function findUrlProduct(string $sku):?string
    {
        $urlSearch = sprintf('https://agrip.pl/?xml=module&module_type=ajax_search&ajax=true&string=%s&search_category', $sku);
        DelivererLogger::log(sprintf('Find URL product %s.', $sku));
        $contents = $this->websiteClient->getContentAnonymous($urlSearch, [
            'headers'=>[
                'x-requested-with' =>'XMLHttpRequest'
            ]
        ]);
        $crawler = $this->getCrawler($contents);
        $href = $this->getAttributeCrawler($crawler->filter('a'), 'href');
        if ($href){
            return sprintf('https://agrip.pl/%s', $href);
        }
        return null;
    }

    /**
     * Get crawler page
     *
     * @param string $urlProduct
     * @return Crawler
     */
    private function getCrawlerPage(string $urlProduct): Crawler
    {
        $contents = $this->websiteClient->getContentAnonymous($urlProduct);
        return $this->getCrawler($contents);
    }

}
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
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use Symfony\Component\DomCrawler\Crawler;

class AspDataProducts implements DataProducts
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
        $this->websiteClient = app(AspWebsiteClient::class, [
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
        if ($this->fillProduct($product)){
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
        $name = $this->getTextCrawler($crawlerProduct->filter('h2.custom-header'));
        if (!$name){
            return false;
        }
        $product->setName($name);
        $this->addEanPropertyProduct($product, $crawlerProduct);
        $this->addImagesProduct($product, $crawlerProduct);
        $this->addAttributesProduct($product, $crawlerProduct);
        $this->addDescriptionProduct($product, $crawlerProduct);
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
        $sku = $product->getProperty('sku');
        $ean = $product->getProperty('ean');
        $unit = $product->getProperty('unit');
        if ($sku){
            $product->addAttribute('SKU', $sku, 20);
        }
        if ($ean){
            $product->addAttribute('EAN', $ean, 30);
        }
        if ($unit){
            $product->addAttribute('Jednostka', $unit, 40);
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
        $explodeUrl = explode('mazonaws.com/', $url);
        $id = $explodeUrl[1] ?? '';
        if (!$id || Str::contains($id, ':')) {
            throw new DelivererAgripException('Invalid ID image product');
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
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, Crawler $crawlerProduct)
    {
        $crawlerProduct->filter('div.product-images div.product-image-box a')->each(function(Crawler $aHtmlElement, $index) use (&$product){
            $main = $index === 0;
            $url = $this->getAttributeCrawler($aHtmlElement, 'href');
            $id = $this->getIdImageProduct($url);;
            $filenameUnique = sprintf('%s.jpg', $id);
            $product->addImage($main, $id, $url, $filenameUnique);
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
     * Add EAN property product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addEanPropertyProduct(ProductSource $product, Crawler $crawlerProduct)
    {
        $ean = '';
        $crawlerProduct->filter('div.codes table tr')->each(function(Crawler $trHtmlElement) use (&$ean){
            $html = $trHtmlElement->html();
            if (Str::contains($html, 'Kod GTIN')){
                $ean = $this->getTextCrawler($trHtmlElement->filter('td'));
            }
        });
        $product->setProperty('ean', $ean);
    }
}
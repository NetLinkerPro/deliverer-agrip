<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\WoocommerceListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WoocommerceWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class WoocommerceListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var WoocommerceWebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var WoocommerceListCategories $listCategories */
    protected $listCategories;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(WoocommerceWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(WoocommerceListCategories::class, [
            'login' => $login,
            'password' => $password,
        ]);
    }

    /**
     * Get
     *
     * @return Generator|ProductSource[]|array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function get(): Generator
    {
        $categories = $this->listCategories->get();
        foreach ($categories as $category) {
            $products = $this->getProducts($category);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    /**
     * Get products
     *
     * @param CategorySource $category
     * @return Generator
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getProducts(CategorySource $category): Generator
    {
        $crawlerPage = $this->getCrawlerPage($category->getUrl(), 1);
        $pages = $this->getPages($crawlerPage);
        foreach (range(1, $pages) as $page) {
            $crawlerPage = $page === 1 ? $crawlerPage : $this->getCrawlerPage($category->getUrl(), $page);
            $products = $this->getProductsCategory($crawlerPage);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    /**
     * Get price
     *
     * @param Crawler $containerHtmlElement
     * @return float
     */
    private function getPrice(Crawler $containerHtmlElement): float
    {
        $textPrice = $this->getTextCrawler($containerHtmlElement->filter('span.woocommerce-Price-amount'));
        $textPrice = str_replace(['.', '&nbsp;', ' ', 'zł'], '', $textPrice);
        $textPrice = str_replace(',', '.', $textPrice);
        return $this->extractFloat($textPrice);
    }

    /**
     * Get name
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getName(Crawler $containerHtmlElement): string
    {
        $name=  $this->getTextCrawler($containerHtmlElement->filter('table.ofit a'));
        return str_replace(';', '', $name);
    }

    /**
     * Get pages
     *
     * @param Crawler $crawlerPage
     * @return int
     */
    private function getPages(Crawler $crawlerPage): int
    {
        $pages = 1;
        $crawlerPage->filter('.woocommerce-pagination ul.page-numbers a.page-numbers')->each(function(Crawler $aElement) use (&$pages){
            $textAElement = $this->getTextCrawler($aElement);
            $foundPages = (int) $textAElement;
            $pages = $foundPages >$pages ? $foundPages : $pages;
        });
        return $pages;
    }

    /**
     * Get products category
     *
     * @param Crawler $crawlerPage
     * @return array
     */
    private function getProductsCategory(Crawler $crawlerPage): array
    {
        $products = $crawlerPage->filter('ul.products > li.product')
            ->each(function (Crawler $containerHtmlElement) {
                $id = $this->getId($containerHtmlElement);
                $url = $this->getUrl($containerHtmlElement);
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                if (!$id) {
                    return null;
                }
                $product = new ProductSource($id, $url);
                $product->setAvailability(1);
                $product->setTax(23);
                $product->setPrice($price);
                $product->setStock($stock);
                return $product;
            });
        return array_filter($products);
    }

    /**
     * Get URL
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getUrl(Crawler $containerHtmlElement): string
    {
       return $this->getAttributeCrawler($containerHtmlElement->filter('.product-wrap > a'), 'href');
    }

    /**
     * Get ID
     *
     * @param Crawler $containerHtmlElement
     * @return string
     * @throws DelivererAgripException
     */
    private function getId(Crawler $containerHtmlElement): string
    {
        $id = $this->getAttributeCrawler($containerHtmlElement->filter('.product-add-to-cart a.button'), 'data-product_sku');
        if (!$id){
            $html = $containerHtmlElement->outerHtml();
            throw new DelivererAgripException('Not found ID product.');
        }
        return $id;
    }

    /**
     * Get stock
     *
     * @param Crawler $containerHtmlElement
     * @return int
     */
    private function getStock(Crawler $containerHtmlElement): int
    {
        $stock = 0;
        $textClass = $this->getAttributeCrawler($containerHtmlElement, 'class');
        if (Str::contains($textClass, ' instock ')){
            $stock = 5;
        }
        return $stock;
    }

    /**
     * Get crawler page
     *
     * @param string $urlCategory
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(string $urlCategory, int $page): Crawler
    {
        if (!Str::endsWith($urlCategory, '/')){
            $urlCategory .= '/';
        }
        $url = sprintf('%spage/%s/', $urlCategory, $page);
        $content = $this->websiteClient->getContents($url);
        $contentExplode = explode('(function ($) {', $content);
        $contents = $contentExplode[0];
        $contentExplode = explode('(jQuery));', $contentExplode[1]);
        $contents = $contents . $contentExplode[1];
        $contents = str_replace('</h3></front>', '</div></a></h3><a></front>', $contents);
//        $html = str_replace(['<!--', '-->'], '', $contents);
//        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        return $this->getCrawler($contents);
    }

    /**
     * Get unit
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getUnit(Crawler $containerHtmlElement): string
    {
        $textUnit = $this->getTextCrawler($containerHtmlElement->filter('table.ofit table.itp tr th'));
        $textUnit = explode('[', $textUnit)[1] ?? '';
        $textUnit = explode(']', $textUnit)[0] ?? '';
        $textUnit = trim($textUnit);
        return mb_strtolower($textUnit);
    }

    /**
     * Set minimum quantity
     *
     * @param ProductSource $product
     * @param Crawler $containerHtmlElement
     */
    private function setMinimumQuantity(ProductSource $product, Crawler $containerHtmlElement): void
    {
        $minimumQuantity = $this->getMinimumQuantity($containerHtmlElement);
        $product->setProperty('minimum_quantity', $minimumQuantity);
        if ($minimumQuantity > 1){
            $newName = sprintf('%s /%s%s', $product->getProperty('long_name'), $minimumQuantity, $product->getProperty('unit'));
            $newPrice = round($minimumQuantity * $product->getPrice(),4);
            $newStock = intval($product->getStock() / $minimumQuantity);
            $product->setProperty('long_name', $newName);
            $product->setPrice($newPrice);
            $product->setStock($newStock);
        }
    }

    /**
     * Get minimum quantity
     *
     * @param Crawler $containerHtmlElement
     * @return int
     */
    private function getMinimumQuantity(Crawler $containerHtmlElement): int
    {
        $trElements = $containerHtmlElement->filter('table.ofit table.itp tr');
        $trElement = $trElements->eq(1);
        $tdElement = $trElement->filter('td')->eq(0);
        $textMinimumQuantity = $this->getTextCrawler($tdElement);
        $textMinimumQuantity = str_replace('+', '', $textMinimumQuantity);
        return $this->extractInteger($textMinimumQuantity);
    }

}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\InsolutionsListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\InsolutionsWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class InsolutionsListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var InsolutionsWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var InsolutionsListCategories $listCategories */
    protected $listCategories;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(InsolutionsWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(InsolutionsListCategories::class, [
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
        $dataFirstPage = $this->getDataPage($category->getUrl(), 1);
        $pages = $this->getPages($dataFirstPage);
        foreach (range(1, $pages) as $page) {
            $dataPage = $page === 1 ? $dataFirstPage : $this->getDataPage($category->getUrl(), $page);
            $crawlerPage = $this->getCrawlerPage($dataPage);
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
     * @throws DelivererAgripException
     */
    private function getPrice(Crawler $containerHtmlElement): float
    {
        $textValid = $this->getTextCrawler($containerHtmlElement->filter('div.price-net'));
        if (!Str::contains($textValid, 'netto')) {
            throw new DelivererAgripException('Price in not netto');
        }
        $textPrice = $this->getTextCrawler($containerHtmlElement->filter('div.price-net span.price'));
        $textPrice = str_replace([' ', ','], ['', '.'], $textPrice);
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
        return $this->getTextCrawler($containerHtmlElement->filter('a.name'));
    }

    /**
     * Get data page
     *
     * @param string $UrlCategory
     * @param int $page
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDataPage(string $UrlCategory, int $page): array
    {
        $url = sprintf('%s?page=%s&query=&sort=symbolAsc&availability=all', $UrlCategory, $page);
        $content = $this->websiteClient->getContentAjax($url);
        return json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get pages
     *
     * @param array $dataPage
     * @return int
     */
    private function getPages(array $dataPage): int
    {
        return $dataPage['dynamicPaginationView']['numberOfPages'] ?? 1;
    }

    /**
     * Get products category
     *
     * @param Crawler $crawlerPage
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsCategory(Crawler $crawlerPage): array
    {
        $products = $crawlerPage->filter('div.product-list > div')
            ->each(function (Crawler $containerHtmlElement) {
                $id = $this->getId($containerHtmlElement);
                $url = $this->getUrl($containerHtmlElement);
                $name = $this->getName($containerHtmlElement);
                $tax = 23;
                $availability = 1;
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                if (!$name || !$id) {
                    return null;
                }
                $product = new ProductSource($id, $url);
                $product->setName($name);
                $product->setProperty('unit', $this->getUnit($containerHtmlElement));
                $product->setTax($tax);
                $product->setAvailability($availability);
                $product->setPrice($price);
                $product->setStock($stock);
                $this->setMinimumOrderProduct($product, $containerHtmlElement);
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
        return sprintf('https://www.agrip.pl%s',$this->getAttributeCrawler($containerHtmlElement->filter('a.name'), 'href'));
    }

    /**
     * Get ID
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getId(Crawler $containerHtmlElement): string
    {
        $url = $this->getUrl($containerHtmlElement);
        $explodeUrl = explode('/product/', $url);
        $explodeUrl = explode(',', $explodeUrl[1]);
        return $explodeUrl[0];
    }

    /**
     * Get stock
     *
     * @param Crawler $containerHtmlElement
     * @return int
     */
    private function getStock(Crawler $containerHtmlElement): int
    {
        $inStockText = $this->getTextCrawler($containerHtmlElement->filter('div.ins-v-in-stock span.in-stock'));
        $inStockText = trim($inStockText);
        return (int)$inStockText;
    }

    /**
     * Get crawler page
     *
     * @param array $dataPage
     * @return Crawler
     */
    private function getCrawlerPage(array $dataPage): Crawler
    {
        $html = $dataPage['html'];
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        return $this->getCrawler($html);
    }

    /**
     * Set minimum order product
     *
     * @param ProductSource $product
     * @param Crawler $containerHtmlElement
     */
    private function setMinimumOrderProduct(ProductSource $product, Crawler $containerHtmlElement)
    {
        $minimumOrder = $this->getMinimumOrder($containerHtmlElement);
        $product->setProperty('minimum_order', $minimumOrder);
        if ($minimumOrder > 1){
            $name = $product->getName();
            $price = $product->getPrice();
            $stock = $product->getStock();
            $name = sprintf('%s /%s%s', $name, $minimumOrder, $product->getProperty('unit'));
            $price = round($price * $minimumOrder, 4);
            $stock = intval($stock / $minimumOrder);
            $product->setName($name);
            $product->setPrice($price);
            $product->setStock($stock);
        }
    }

    /**
     * Get minimum order
     *
     * @param Crawler $containerHtmlElement
     * @return int
     */
    private function getMinimumOrder(Crawler $containerHtmlElement): int
    {
        $textMinimumOrder = $this->getTextCrawler($containerHtmlElement->filter('div.qty-multiplier strong'));
        $textMinimumOrder = str_replace(' ', '', $textMinimumOrder);
        if (!$textMinimumOrder) {
            return 1;
        }
        return (int)$textMinimumOrder;
    }

    /**
     * Get unit
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getUnit(Crawler $containerHtmlElement): string
    {
        return $this->getTextCrawler($containerHtmlElement->filter('span.unit'));
    }

}
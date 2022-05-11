<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\ComarchListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\ComarchWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class ComarchListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var ComarchWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var ComarchListCategories $listCategories */
    protected $listCategories;

    /**
     * SupremisB2bListCategories constructor
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
        $this->listCategories = app(ComarchListCategories::class, [
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
        $deepestCategory = $this->getDeepestCategory($category);
        $crawlerPage = $this->getCrawlerPage($deepestCategory->getUrl(), 1);
        $pages = $this->getPages($crawlerPage);
        foreach (range(1, $pages) as $page) {
            $crawlerPage = $page === 1 ? $crawlerPage : $this->getCrawlerPage($category->getUrl(), $page);
            $products = $this->getProductsCategory($crawlerPage, $category);
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
        $textPrice = $this->getTextCrawler($containerHtmlElement->filter('span.total-price-on-list-view-lq'));
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
        return $this->getTextCrawler($containerHtmlElement->filter('h3.product-name-ui'));
    }

    /**
     * Get data page
     *
     * @param string $urlCategory
     * @param int $page
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDataPage(string $urlCategory, int $page): array
    {
        $url = sprintf('%s?pageId=%s&__template=products%%2Fproducts-list.html&__include=', $urlCategory, $page);
        $content = $this->websiteClient->getContentAjax($url);
        return json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get pages
     *
     * @param Crawler $crawlerPage
     * @return int
     */
    private function getPages(Crawler $crawlerPage): int
    {
        $textPages = $this->getTextCrawler($crawlerPage->filter('span.page-amount-ui'));
        $textPages = str_replace('z ', '', $textPages);
        $pages = (int)$textPages;
        if (!$pages) {
            $pages = 1;
        }
        return $pages;
    }

    /**
     * Get products category
     *
     * @param Crawler $crawlerPage
     * @param CategorySource $category
     * @return array
     */
    private function getProductsCategory(Crawler $crawlerPage, CategorySource $category): array
    {
        $products = $crawlerPage->filter('div.product-item-ui')
            ->each(function (Crawler $containerHtmlElement) use (&$category) {
                $id = $this->getId($containerHtmlElement);
                $url = $this->getUrl($containerHtmlElement);
                $name = $this->getName($containerHtmlElement);
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                if (!$name || !$id) {
                    return null;
                }
                $product = new ProductSource($id, $url);
                $product->setName($name);
                $product->setProperty('unit', $this->getUnit($containerHtmlElement));
                $product->setProperty('SKU', $this->getSku($containerHtmlElement));
                $product->setProperty('manufacturer', $this->getManufacturer($containerHtmlElement));
                $product->setProperty('weight', $this->getAttribute('Waga', $containerHtmlElement));
                $product->setAvailability(1);
                $product->setPrice($price);
                $product->setStock($stock);
                $product->addCategory($category);
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
        return sprintf('https://www.agrip.pl/%s', $this->getAttributeCrawler($containerHtmlElement->filter('a.product-link-ui'), 'href'));
    }

    /**
     * Get ID
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getId(Crawler $containerHtmlElement): string
    {
        return $this->getAttributeCrawler($containerHtmlElement, 'data-product-id');
    }

    /**
     * Get stock
     *
     * @param Crawler $containerHtmlElement
     * @return int
     */
    private function getStock(Crawler $containerHtmlElement): int
    {
        $inStockText = $this->getAttributeCrawler($containerHtmlElement->filter('input[name="quantity"]'), 'data-max');
        return (int)$inStockText;
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
        $dataPage = $this->getDataPage($urlCategory, $page);
        $html = $dataPage['template'];
        $html = str_replace(['<!--', '-->'], '', $html);
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        return $this->getCrawler($html);
    }

    /**
     * Get unit
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getUnit(Crawler $containerHtmlElement): string
    {
        $textUnit = $this->getTextCrawler($containerHtmlElement->filter('.price-ui .price-label-ui'));
        $textUnit = explode('/', $textUnit)[1] ?? '';
        $textUnit = trim($textUnit);
        $textUnit = mb_strtolower($textUnit);
        if ($textUnit === 'szt') {
            $textUnit .= '.';
        }
        return $textUnit;
    }


    /**
     * Get SKU
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getSku(Crawler $containerHtmlElement): string
    {
        $skuText = $this->getTextCrawler($containerHtmlElement->filter('span.product-code-ui'));
        $skuText = str_replace('P/N: ', '', $skuText);
        return trim($skuText);
    }

    /**
     * Get manufacturer
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getManufacturer(Crawler $containerHtmlElement): string
    {
        $manufacturer = $this->getAttribute('Producent', $containerHtmlElement);
        if (!$manufacturer){
            $manufacturer = $this->getAttribute('Marka', $containerHtmlElement);
        }
        if (mb_strtolower($manufacturer) === 'agrip') {
            $manufacturer = '';
        }
        return $manufacturer;
    }

    /**
     * Get attribute
     *
     * @param string $name
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getAttribute(string $name, Crawler $containerHtmlElement): string{
        $value = '';
        $containerHtmlElement->filter('.attributes-ui .clear-after-ui')
            ->each(function (Crawler $attributeElementDiv) use (&$name, &$value) {
                $nameAttribute = $this->getTextCrawler($attributeElementDiv->filter('.f-left-ui'));
                if (!$value && mb_strtolower($nameAttribute) === mb_strtolower($name)){
                    $value = $this->getTextCrawler($attributeElementDiv->filter('.f-right-ui'));
                }
            });
        return $value;
    }

    /**
     * @param CategorySource $category
     * @return CategorySource
     */
    private function getDeepestCategory(CategorySource $category):CategorySource
    {
        $categoryDeepest = $category;
        while($categoryDeepest){
            $categoryChild = $categoryDeepest->getChildren()[0] ?? null;
            if ($categoryChild){
                $categoryDeepest = $categoryChild;
            } else {
                break;
            }
        }
        return $categoryDeepest;
    }
}
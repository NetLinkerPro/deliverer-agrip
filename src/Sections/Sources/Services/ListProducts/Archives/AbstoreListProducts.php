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
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\AbstoreListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\WoocommerceListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AbstoreWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WoocommerceWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class AbstoreListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var AbstoreWebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var AbstoreListCategories $listCategories */
    protected $listCategories;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(AbstoreWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(AbstoreListCategories::class, [
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
        $crawlerPage = $this->getCrawlerPage($category, 1);
        $pages = $this->getPages($crawlerPage);
        foreach (range(1, $pages) as $page) {
            $crawlerPage = $page === 1 ? $crawlerPage : $this->getCrawlerPage($category, $page);
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
        $textPrice = $this->getTextCrawler($containerHtmlElement->filter('.abs-item-price-amount'));
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
        $name = $this->getTextCrawler($containerHtmlElement->filter('table.ofit a'));
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
        $crawlerPage->filter('ul.pagination li a')->each(function (Crawler $aElement) use (&$pages) {
            $textAElement = $this->getTextCrawler($aElement);
            $foundPages = (int)$textAElement;
            $pages = $foundPages > $pages ? $foundPages : $pages;
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
        $products = $crawlerPage->filter('tr.product-list-item')
            ->each(function (Crawler $containerHtmlElement) {
                $id = $this->getId($containerHtmlElement);
                $url = $this->getUrl($containerHtmlElement);
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                $tax = $this->getTax($containerHtmlElement);
                if (!$id) {
                    return null;
                }
                $product = new ProductSource($id, $url);
                $product->setAvailability(1);
                $product->setTax($tax);
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
        $href = $this->getAttributeCrawler($containerHtmlElement->filter('td.abs-col-name a'), 'href');
        return sprintf('https://agrip.abstore.pl%s', $href);
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
        $id = $this->getAttributeCrawler($containerHtmlElement->filter('td.abs-col-name a'), 'href');
       $id = explode(',p', $id)[1];
       $id = explode(',', $id)[0];
        if (!$id) {
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
        $text = $this->getTextCrawler($containerHtmlElement->filter('span.abs-avail-txt span'));
        if (Str::contains($text, '(')) {
            $stockFromText = $this->extractInteger($text);
            if ($stockFromText){
                $stock = $stockFromText;
            }
        }
        return $stock;
    }

    /**
     * Get crawler page
     *
     * @param CategorySource $category
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(CategorySource $category, int $page): Crawler
    {
        $content = $this->websiteClient->getContentAjax('https://agrip.abstore.pl/ajax/fts/navigationstep', [
            RequestOptions::FORM_PARAMS => [
                '_ref' => sprintf('a,s1001,c%s,20,%s,pl.html', $category->getId(), $page),
                '_history' => 'true',
            ]
        ]);
        DelivererLogger::log(sprintf('Category %s, page %s.', $category->getUrl(), $page));
        $data = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
        $html = sprintf('<div>%s%s%s%s%s</div>',
            $data['data']['viewHTML'] ?? '',
            $data['data']['pagerHTML'] ?? '',
            $data['data']['footerHTML'] ?? '',
            $data['data']['sortHTML'] ?? '',
            $data['data']['availHTML'] ?? '');
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
        if ($minimumQuantity > 1) {
            $newName = sprintf('%s /%s%s', $product->getProperty('long_name'), $minimumQuantity, $product->getProperty('unit'));
            $newPrice = round($minimumQuantity * $product->getPrice(), 4);
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

    /**
     * Get tax
     *
     * @param Crawler $containerHtmlElement
     * @return int
     */
    private function getTax(Crawler $containerHtmlElement): int
    {
        $spanText = $this->getTextCrawler($containerHtmlElement->filter('span.abs-item-price-breakdown'));
        $spanText = explode('w tym', $spanText)[1];
        return $this->extractInteger($spanText);
    }

}
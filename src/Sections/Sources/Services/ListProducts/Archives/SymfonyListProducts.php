<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Enums\TypeAttributeCrawler;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\CsvFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AspWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Dedicated1WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SymfonyWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class SymfonyListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor;

    /** @var WebsiteClient $websiteClient */
    protected $websiteClient;

    /**
     * SoapListProducts constructor
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
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     * @throws GuzzleException
     */
    public function get(?CategorySource $category = null): Generator
    {
        $products = $this->getProducts($category);
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get products
     *
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     */
    private function getProducts(?CategorySource $category): Generator
    {
        $idCategory = $this->getIdCategory($category);
        $crawlerFirstPage = $this->getCrawlerPage($idCategory, 1);
        $quantityPages = $this->getQuantityPages($crawlerFirstPage);
        for ($page = 1; $page <= $quantityPages; $page++) {
            $crawlerPage = $page === 1 ? $crawlerFirstPage : $this->getCrawlerPage($idCategory, $page);
            $products = $this->getProductsCrawlerPage($crawlerPage, $category);
            foreach ($products as $product){
                yield $product;
            }
        }
    }

    /**
     * Get product
     *
     * @param Crawler $containerProduct
     * @param CategorySource $category
     * @return ProductSource|null
     */
    private function getProduct(Crawler $containerProduct, CategorySource $category): ?ProductSource
    {
        $id = $this->getAttributeCrawler($containerProduct->filter('form.buy'), 'data-id');
        $price = $this->getPrice($containerProduct);
        if (!$price){
            return null;
        }
        $stock = $this->getStock($containerProduct);
        $unit = $this->getUnit($containerProduct);
        $minimumQuantity = $this->getAttributeCrawler($containerProduct->filter('input[name="ilosc"]'), 'data-val', TypeAttributeCrawler::INTEGER_TYPE) ?? 1;
        $tax = 23;
        $availability = 1;
        $name = $this->getTextCrawler($containerProduct->filter('a.title'));
        $url = sprintf('https://agrip.de/-/p/%s', $id);
        if ($minimumQuantity > 1){
            $name = sprintf('%s /%s%s', $name, $minimumQuantity, $unit);
            $price = round($price * $minimumQuantity, 2);
        }
        $product = new ProductSource($id, $url);
        $product->setCategories([$category]);
        $product->setPrice($price);
        $product->setTax($tax);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $product->setName($name);
        $product->setProperty('unit', $unit);
        $product->setProperty('minimum_quantity', $minimumQuantity);
        return $product;
    }

    /**
     * Get ID category
     *
     * @param CategorySource|null $category
     * @return string
     */
    private function getIdCategory(?CategorySource $category): string
    {
        $lastCategory = $category;
        while ($lastCategory->getChildren()) {
            $lastCategory = $lastCategory->getChildren()[0];
        }
        return $lastCategory->getId();
    }

    /**
     * Get content page
     *
     * @param string $idCategory
     * @param int $page
     * @return string
     */
    private function getContentPage(string $idCategory, int $page): string
    {
        DelivererLogger::log(sprintf('Get data page %s, for category %s.', $page, $idCategory));
        return $this->websiteClient->getContentAjax('https://www.agrip.pl/katalog/availView', [
            RequestOptions::FORM_PARAMS => [
                'val' => true,
                'id_kategorii' => $idCategory,
                'page' => $page,
            ],
        ]);
    }

    /**
     * Get products data page
     *
     * @param Crawler $crawlerPage
     * @param CategorySource $category
     * @return array
     */
    private function getProductsCrawlerPage(Crawler $crawlerPage, CategorySource $category): array
    {
        $products = [];
        $crawlerPage->filter('div.items > div.product')->each(function(Crawler $containerProduct) use (&$products, &$category){
            $product = $this->getProduct($containerProduct, $category);
            if ($product){
                array_push($products, $product);
            }
        });
        return $products;
    }

    /**
     * Get crawler page
     *
     * @param string $idCategory
     * @param int $page
     * @return Crawler
     */
    private function getCrawlerPage(string $idCategory, int $page): Crawler
    {
        $contentPage = $this->getContentPage($idCategory, $page);
        return $this->getCrawler($contentPage);
    }

    /**
     * Get data price
     *
     * @param Crawler $containerProduct
     * @return float
     */
    private function getPrice(Crawler $containerProduct): float
    {
        $price = 0;
        $containerProduct->filter('div.price')
            ->each(function(Crawler $priceContainer) use (&$price){
               $class = $priceContainer->attr('class');
               if (!Str::contains($class, 'old')){
                   $textPrice = $this->getTextCrawler($priceContainer);
                   $price = $this->extractFloat($textPrice);
               }
            });
        return $price;
    }

    /**
     * Get quantity pages
     *
     * @param Crawler $crawlerFirstPage
     * @return int
     */
    private function getQuantityPages(Crawler $crawlerFirstPage): int
    {
        $quantityPages = 1;
        $crawlerFirstPage->filter('div.navigation div.page a')
            ->each(function(Crawler $aHtmlElement) use(&$quantityPages){
                $text = $this->getTextCrawler($aHtmlElement);
                $number = intval($text);
                if ($number>$quantityPages){
                    $quantityPages = $number;
                }
            });
        return $quantityPages;
    }

    /**
     * Get stock
     *
     * @param Crawler $containerProduct
     * @return int
     */
    private function getStock(Crawler $containerProduct): int
    {
        $stock = 0;
        $textAvailable = $this->getTextCrawler($containerProduct->filter('div.available'));
        if (!Str::contains($textAvailable, 'Zapytaj o dostępność')){
            $stock = 5;
        }
        return $stock;
    }

    /**
     * Get unit
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getUnit(Crawler $containerProduct): string
    {
        $unit = $this->getAttributeCrawler($containerProduct->filter('input[name="ilosc"]'), 'data-jm');
        if (!Str::endsWith($unit, '.') && $unit === 'szt'){
            $unit = 'szt.';
        }
        return $unit;
    }

}
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
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\CsvFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AspWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Dedicated1WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class AspListProducts implements ListProducts
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
        $this->websiteClient = app(AspWebsiteClient::class, [
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
     * @return array|ProductSource[]
     */
    private function getProducts(?CategorySource $category): array
    {
        $idCategory = $this->getIdCategory($category);
        $products = [];
        for ($page = 1; $page < 1000; $page++) {
            $dataPage = $this->getDataPage($idCategory, $page);
            $products = array_merge($products, $this->getProductsDataPage($dataPage, $category));
            if ($this->isProductsEndCategory($dataPage)){
                break;
            }
        }
        return $products;
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
        $id = $this->getAttributeCrawler($containerProduct->filter('button.add-to-cart'), 'data-articleid');
        $dataPrice = $this->getDataPrice($id);
        $stock = (int) $this->getTextCrawler($containerProduct->filter('div.available-stock-state')->eq(0));
        $unit = $this->getTextCrawler($containerProduct->filter('div.available-stock-state')->eq(1));
        $tax = $dataPrice['price']['VatRate'];
        $availability = 1;
        $price = $dataPrice['price']['Price'];
        $sku = $this->getTextCrawler($containerProduct->filter('small.pd-code'));
        $url = sprintf('https://b2b.agrip.net.pl/Towar/%s', $id);
        $product = new ProductSource($id, $url);
        $product->setCategories([$category]);
        $product->setPrice($price);
        $product->setTax($tax);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $product->setProperty('unit', $unit);
        $product->setProperty('sku', $sku);
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
     * Get data page
     *
     * @param string $idCategory
     * @param int $page
     * @return array
     */
    private function getDataPage(string $idCategory, int $page): array
    {
        DelivererLogger::log(sprintf('Get data page %s, for category %s.', $page, $idCategory));
        $contentResponse = $this->websiteClient->getContentAjax('https://b2b.agrip.net.pl/Article/GetAtricleGrid', [
            RequestOptions::FORM_PARAMS => [
                'PageNumber' => (string) $page -1,
                'PageSize' => '28',
                'Limit' => '',
                'ColumnNumber' => '4',
                'ItemWidth' => '3',
                'CategoryId' => $idCategory,
                'Search' => '',
                'FavoredFilter' => 'false',
                'OrderBy' => 'offerStockPriceId',
                'OrderByType' => 'desc',
                'InStockFilter' => 'true',
                'UpdateContactFavoredOffersFilter' => 'true',
            ],
            'headers' => [
                'x-requested-with' => 'XMLHttpRequest',
            ]
        ]);
        $data = json_decode($contentResponse, true, 512, JSON_UNESCAPED_UNICODE);
        return $data['Data'];
    }

    /**
     * Is products end category
     *
     * @param array $dataPage
     * @return bool
     */
    private function isProductsEndCategory(array $dataPage): bool
    {
        return $dataPage['stopLoading'] === true;
    }

    /**
     * Get products data page
     *
     * @param array $dataPage
     * @param CategorySource $category
     * @return array
     */
    private function getProductsDataPage(array $dataPage, CategorySource $category): array
    {
        $products = [];
        $crawlerPage = $this->getCrawlerPage($dataPage);
        $crawlerPage->filter('div.ibox-content.product-box')->each(function(Crawler $containerProduct) use (&$products, &$category){
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
     * @param array $dataPage
     * @return Crawler
     */
    private function getCrawlerPage(array $dataPage): Crawler
    {
        return $this->getCrawler($dataPage['html']);
    }

    /**
     * Get data price
     *
     * @param string $idProduct
     * @return float
     */
    private function getDataPrice(string $idProduct): array
    {
       $contentResponse = $this->websiteClient->getContentAjax('https://b2b.agrip.net.pl/Article/GetPrice', [
           RequestOptions::FORM_PARAMS => [
               'id' => $idProduct,
               'offerId' => $idProduct,
           ],
           'headers' => [
               'x-requested-with' => 'XMLHttpRequest',
           ]
       ]);
       return json_decode($contentResponse, true)['Data'];
    }

}
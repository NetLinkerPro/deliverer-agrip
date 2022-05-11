<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Comarch2ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Comarch2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class Comarch2ListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var Comarch2WebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var Comarch2ListCategories $listCategories */
    protected $listCategories;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     * @param string $login2
     */
    public function __construct(string $login, string $password, string $login2)
    {
        $this->websiteClient = app(Comarch2WebsiteClient::class, [
            'login' => $login,
            'password' => $password,
            'login2' =>$login2,
        ]);
        $this->listCategories = app(Comarch2ListCategories::class, [
            'login' => $login,
            'password' => $password,
            'login2' =>$login2,
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
        $jsonPage = $this->getJsonPage($deepestCategory, 1);
        if (!$jsonPage){
            return;
        }
        $pages = $this->getPages($jsonPage);
        foreach (range(1, $pages) as $page) {
            $jsonPage = $page === 1 ? $jsonPage : $this->getJsonPage($category, $page);
            if (!$jsonPage){
                continue;
            }
            $products = $this->getProductsCategory($jsonPage, $category);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    /**
     * Get price
     *
     * @param array $jsonLive
     * @param string $priceType
     * @return float
     */
    private function getPrice(array $jsonLive, string $priceType = 'netPrice'): float
    {
        $textPrice = $jsonLive['items']['set1'][0][$priceType] ?? '0';
       $textPrice = str_replace(['.', ' ', '&nbsp;', ' '], '', $textPrice);
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
        return $this->getTextCrawler($containerHtmlElement->filter('h3.product-name-ui'));
    }

    /**
     * Get pages
     *
     * @param array $jsonPage
     * @return int
     */
    private function getPages(array $jsonPage): int
    {
        $totalPages = $jsonPage['paging']['totalPages'] ?? 0;
        if (!$totalPages){
            $totalPages = 1;
        }
        return $totalPages;
    }

    /**
     * Get products category
     *
     * @param array $jsonPage
     * @param CategorySource $category
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getProductsCategory(array $jsonPage, CategorySource $category): array
    {
        $jsonProducts = $jsonPage['products'] ?? [];
        $products = [];
        foreach ($jsonProducts as $jsonProduct){
            if (!$jsonProduct['availability']){
                continue;
            }
            $categoryShipping = $jsonProduct['_KategoriaWysylki'];
            if (!in_array($categoryShipping, ['A', 'B', 'C','D','E', 'NN'])){
                continue;
            }
            $id = $this->getId($jsonProduct);
            $url = $this->getUrl($jsonProduct, $category);
            $jsonLive = $this->getJsonLive($id);
            $price = $this->getPrice($jsonLive);
            if (!$price){
                DelivererLogger::log("Not found price.");
                continue;
            }
            $stock = $this->getStock($jsonLive);
            $tax = $this->getTax($jsonLive);
            if (!$id) {
               continue;
            }
            $product = new ProductSource($id, $url);
            $product->setTax($tax);
            $product->setAvailability(1);
            $product->setPrice($price);
            $product->setStock($stock);
            $product->addCategory($category);
            $unit = $jsonLive['items']['set1'][0]['basicUnit'] ?? '';
            if ($unit){
                $product->setProperty('unit', $unit);
            }
            array_push($products, $product);
        }
        return $products;
    }

    /**
     * Get URL
     *
     * @param array $jsonProduct
     * @param CategorySource $category
     * @return string
     */
    private function getUrl(array $jsonProduct, CategorySource $category): string
    {
        $id = $this->getId($jsonProduct);
        $deepestCategory = $this->getDeepestCategory($category);
        return sprintf('http://www.b2b.agrip.info/itemdetails/%s?group=%s', $id, $deepestCategory->getId());
    }

    /**
     * Get ID
     *
     * @param array $jsonProduct
     * @return string
     */
    private function getId(array $jsonProduct): string
    {
        return $jsonProduct['id'];
    }

    /**
     * Get stock
     *
     * @param array $jsonLive
     * @return int
     */
    private function getStock(array $jsonLive): int
    {
        $inStockText = $jsonLive['items']['set1'][0]['stockLevel'] ?? '0';
        return (int)$inStockText;
    }

    /**
     * Get crawler page
     *
     * @param CategorySource $category
     * @param int $page
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getJsonPage(CategorySource $category, int $page): array
    {
        $url = sprintf('http://www.b2b.agrip.info/api/items/?groupId=%s&filter=&onlyAvailable=false&isGlobalFilter=false&filterInGroup=false&features=&attributes=&pageNumber=%s', $category->getId(), $page);
        try{
            $content = $this->websiteClient->getContentAjax($url, [], 'GET', '{"products":[');
        } catch (ClientException $exception){
            if ($exception->getCode() === 403){
                DelivererLogger::log("Code exception 403.");
                return [];
            } else {
                throw $exception;
            }
        }
        return json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
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
     * Get deepest category
     *
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

    /**
     * Get JSON live
     *
     * @param string $id
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getJsonLive(string $id): array
    {
        $url = sprintf('http://www.b2b.agrip.info/api/items/pricesasync/?articleId=%s&warehouseId=1&features=', $id);
        $contents = $this->websiteClient->getContentAjax($url, [], 'GET','{"items":{');
        return json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get tax
     *
     * @param array $jsonLive
     * @return int
     */
    private function getTax(array $jsonLive):int
    {
        $netPrice = $this->getPrice($jsonLive);
        $bruttoPrice = $this->getPrice($jsonLive, 'grossPrice');
        $tax = $bruttoPrice / $netPrice;
        return (int) round(($tax - 1) * 100);
    }
}
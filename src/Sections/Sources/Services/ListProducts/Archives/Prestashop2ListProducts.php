<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Prestashop2ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Prestashop2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class Prestashop2ListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var Prestashop2WebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var Prestashop2ListCategories $listCategories */
    protected $listCategories;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(Prestashop2WebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(Prestashop2ListCategories::class, [
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
        $dataPage = $this->getDataPage($deepestCategory->getUrl(), 1);
        $pages = $this->getPages($dataPage);
        foreach (range(1, $pages) as $page) {
            $dataPage = $page === 1 ? $dataPage : $this->getDataPage($deepestCategory->getUrl(), $page);
            $products = $this->getProductsCategory($dataPage, $category);
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
        $textPrice = $this->getAttributeCrawler($containerHtmlElement->filter('span.product-price'), 'content');
        $textPrice = str_replace(['&nbsp;', ' ', 'zł'], '', $textPrice);
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
     * @param array $dataPage
     * @return int
     */
    private function getPages(array $dataPage): int
    {
        return $dataPage['pagination']['pages_count'] ?? 1;
    }

    /**
     * Get products category
     *
     * @param array $dataPage
     * @param CategorySource $category
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsCategory(array $dataPage, CategorySource $category): array
    {
        $crawlerPage = new Crawler();
        $crawlerPage->addHtmlContent($dataPage['rendered_products']);
        $products = $crawlerPage->filter('article.product-miniature')
            ->each(function (Crawler $containerHtmlElement) use (&$category) {
                $id = $this->getId($containerHtmlElement);
                $url = $this->getUrl($containerHtmlElement);
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                if (!$id) {
                    return null;
                }
                $product = new ProductSource($id, $url);
                $product->setAvailability(1);
                $product->setPrice($price);
                $product->setStock($stock);
                $product->setCategories([$category]);
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
       return $this->getAttributeCrawler($containerHtmlElement->filter('.product-title > a'), 'href');
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
        $id = $this->getAttributeCrawler($containerHtmlElement, 'data-id-product');
        if (!$id){
            throw new DelivererAgripException('Not found ID product.');
        }
        return $id;
    }

    /**
     * Get stock
     *
     * @param Crawler $containerHtmlElement
     * @return int
     * @throws DelivererAgripException
     */
    private function getStock(Crawler $containerHtmlElement): int
    {
        $productReference = $this->getProductReference("STOCK", $containerHtmlElement);
        if (!$productReference){
            throw new DelivererAgripException('Not found product reference.');
        }
        return $this->extractInteger($productReference);
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
        if (!Str::endsWith($urlCategory, '/')){
            $urlCategory .= '/';
        }
        $url = sprintf('%s?page=%s&from-xhr', $urlCategory, $page);
        $content = $this->websiteClient->getContentAjax($url, [], 'GET', '{"rendered_products_top":"');
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
     * Get product reference
     *
     * @param string $name
     * @param Crawler $containerHtmlElement
     * @return string|null
     */
    private function getProductReference(string $name, Crawler $containerHtmlElement): ?string
    {
        $productReference = null;
        $containerHtmlElement->filter('div.product-reference')
            ->each(function(Crawler $productReferenceElement) use (&$name, &$productReference){
                $text = trim($productReferenceElement->text());
                if (Str::contains(mb_strtolower($text), mb_strtolower(sprintf('%s:', $name)))){
                    $productReference = explode(':', $text, 2)[1];
                }
            });
        return $productReference;
    }

}
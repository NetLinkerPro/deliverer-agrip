<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;


use Generator;
use Illuminate\Support\Facades\Cache;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Magento2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class Magento2ListProducts implements ListProducts
{
    const KEY_CACHE_COUNT_PAGES = 'agrip_magento_2_list_products_count_pages';

    use CrawlerHtml, NumberExtractor;

    /** @var WebsiteClient $websiteClient */
    private $websiteClient;

    /** @var string|null $fromAddProduct */
    protected $fromAddProduct;

    /**
     * Magento2ListCategories constructor
     */
    public function __construct(array $configuration = [])
    {
        $this->fromAddProduct = $configuration['from_add_product'] ?? null;
        $this->websiteClient = app(Magento2WebsiteClient::class);
    }

    /**
     * Get
     *
     * @param CategorySource $category
     * @return Generator|ProductSource[]
     */
    public function get(CategorySource $category): Generator
    {
        $quantityPages = $this->countPagesCache($category);
        $products = $this->getProducts($category, $quantityPages);
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Count pages cache
     *
     * @param CategorySource $category
     * @return int
     */
    private function countPagesCache(CategorySource $category): int
    {
        $keyCache = sprintf('%s_%s', self::KEY_CACHE_COUNT_PAGES, $category->getId());
        return Cache::remember($keyCache, 17200, function () use (&$category) {
            return $this->countPages($category);
        });
    }

    /**
     * Count pages
     *
     * @param CategorySource $category
     * @return int
     */
    private function countPages(CategorySource $category): int
    {
        $url = $this->getUrlCategory($category);
        $content = $this->websiteClient->getContentAnonymous($url);
        $crawler = $this->getCrawler($content);
        $quantityPages = 1;
        $crawler->filter('label.cs-pagination__page-provider-label')
            ->each(function (Crawler $label) use (&$quantityPages) {
                $content = $this->getTextCrawler($label);
                $maxQuantityPages = $this->extractInteger($content);
                $quantityPages = $maxQuantityPages > $quantityPages ? $maxQuantityPages : $quantityPages;
            });
        return $quantityPages;
    }

    /**
     * Get URL category
     *
     * @param CategorySource $category
     * @param int $page
     * @return string
     */
    private function getUrlCategory(CategorySource $category, int $page = 1): string
    {
        return sprintf('%s?p=%s&product_list_limit=60', $category->getUrl(), $page);
    }

    /**
     * Get products
     *
     * @param CategorySource $category
     * @param int $quantityPages
     * @return Generator
     */
    private function getProducts(CategorySource $category, int $quantityPages): Generator
    {
        foreach (range(1, $quantityPages) as $numberPage) {
            DelivererLogger::log(sprintf('Get page %s from %s pages for category %s.', $numberPage, $quantityPages, $category->getUrl()));
            if ($this->fromAddProduct){
                if (!$this->unlockFromAddProduct($category, $numberPage)){
                    continue;
                }
            }
            $urlCategory = $this->getUrlCategory($category, $numberPage);
            $contentPage = $this->websiteClient->getContentAnonymous($urlCategory);
            $crawlerPage = $this->getCrawler($contentPage);
            $products = $this->getProductsPage($crawlerPage);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    /**
     * Get products page
     *0
     * @param Crawler $crawlerPage
     * @return ProductSource[]
     */
    private function getProductsPage(Crawler $crawlerPage): array
    {
        $products = [];
        $crawlerPage->filter('ol.products.items > li')
            ->each(function (Crawler $li) use (&$products) {
                $productsContainer = $this->getProductsContainer($li);
                $products = array_merge($products, $productsContainer);
            });
        return $products;
    }

    /**
     * Get products container
     *
     * @param Crawler $container
     * @return array
     */
    private function getProductsContainer(Crawler $container): array
    {
        $withoutVariantId = $this->getWithoutVariantId($container);
        $products = [];
        $url = sprintf('https://agrip.de/catalog/product/view/id/%s', $withoutVariantId);
        $product = new ProductSource($withoutVariantId, $url);
        array_push($products, $product);
        return $products;
    }

    /**
     * Get without variant ID
     *
     * @param Crawler $container
     * @return string
     */
    private function getWithoutVariantId(Crawler $container): string
    {
       return $this->getAttributeCrawler($container->filter('div[data-role="priceBox"]'), 'data-product-id');
    }

    /**
     * Unlock from add product
     *
     * @param CategorySource $category
     * @param int $numberPage
     * @return bool
     */
    private function unlockFromAddProduct(CategorySource $category, int $numberPage): bool
    {
        $explodeFromAddProduct = explode(';', $this->fromAddProduct);
        $idMainCategoryFrom = $explodeFromAddProduct[0] ?? '';
        $numberPageCategoryFrom = $explodeFromAddProduct[1] ?? '';
        if ($category->getId() === (string) $idMainCategoryFrom && (string) $numberPage === (string) $numberPageCategoryFrom){
            $this->fromAddProduct = null;
            return true;
        } else {
            return false;
        }
    }
}
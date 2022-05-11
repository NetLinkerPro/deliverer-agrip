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
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\WmcListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WmcWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class WmcListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor;

    /** @var WmcWebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var WmcListCategories $listCategories */
    protected $listCategories;

    /**
     * SoapListProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(WmcWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(WmcListCategories::class, [
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
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     */
    private function getProducts(?CategorySource $category): Generator
    {
        $deepestCategory = $this->getDeepestCategory($category);
        $crawlerFirstPage = $this->getCrawlerPage($deepestCategory, 1);;
        $quantityPages = $this->getQuantityPages($crawlerFirstPage);
        for ($page = 1; $page <= $quantityPages; $page++) {
            $crawlerPage = $page === 1 ? $crawlerFirstPage : $this->getCrawlerPage($deepestCategory, $page);
            $products = $this->getProductsCrawlerPage($category, $crawlerPage);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    /**
     * Get deepest category
     *
     * @param CategorySource $category
     * @return CategorySource
     */
    private function getDeepestCategory(CategorySource $category): CategorySource
    {
        $categoryDeepest = $category;
        while ($categoryDeepest) {
            $categoryChild = $categoryDeepest->getChildren()[0] ?? null;
            if ($categoryChild) {
                $categoryDeepest = $categoryChild;
            } else {
                break;
            }
        }
        return $categoryDeepest;
    }

    /**
     * Get product
     *
     * @param Crawler $containerProduct
     * @param CategorySource $category
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function getProduct(Crawler $containerProduct, CategorySource $category): ?ProductSource
    {
        $id = $this->getIdProduct($containerProduct);
        if (!$id) {
            return null;
        }
        DelivererLogger::log(sprintf('Product ID: %s.', $id));
        $price = $this->getPrice($containerProduct);
        if (!$price) {
            return null;
        }
        $stock = $this->getStock($containerProduct);
        $availability = 1;
        $url = sprintf('https://b2b.agrip.pl/wmc/product/product/%s/show', $id);
        $product = new ProductSource($id, $url);
        $product->setProperty('unit', $this->getUnit($containerProduct));
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $product->setCategories([$category]);
        $product->setName(str_replace(', ', ' ', $this->getTextCrawler($containerProduct->filter('h5 a'))));
        return $product;
    }

    /**
     * Get content page
     *
     * @param CategorySource $deepestCategory
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(CategorySource $deepestCategory, int $page): Crawler
    {
        $idCategory = $deepestCategory->getId();
        $explodeIdCategory = explode('__', $idCategory);
        $idCategory = $explodeIdCategory[sizeof($explodeIdCategory) - 1];
        DelivererLogger::log(sprintf('Get data page %s.', $page));
        $url = sprintf('https://b2b.agrip.pl/wmc/order/order/list-product/%s?category=%s', $page, $idCategory);
        $contents = $this->websiteClient->getContentAjax($url, [], 'POST', '<table class="table search-table">');
        return $this->getCrawler($contents);
    }

    /**
     * Get products data page
     *
     * @param Crawler $crawlerPage
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsCrawlerPage(CategorySource $category, Crawler $crawlerPage): array
    {
        $products = [];
        $crawlerPage->filter('table.search-table tr')->each(function (Crawler $containerProduct) use (&$products, &$category) {
            $tds = $containerProduct->filter('td');
            if ($tds->count() > 2) {
                $product = $this->getProduct($containerProduct, $category);
                if ($product) {
                    array_push($products, $product);
                }
            }
        });
        return $products;
    }

    /**
     * Get data price
     *
     * @param Crawler $containerProduct
     * @return float|null
     * @throws DelivererAgripException
     */
    private function getPrice(Crawler $containerProduct): ?float
    {
        $text = $this->getAttributeCrawler($containerProduct->filter('input.search-product-quantity'), 'data-price');
        $text = str_replace([' ', ';&nbsp;', 'PLN', ','], '', $text);
        $text = str_replace(',', '.', $text);
        $price = $this->extractFloat($text);
        if ($price === null) {
            return null;
        }
        return $price;
    }

    /**
     * Get quantity pages
     *
     * @param Crawler $crawlerFirstPage
     * @return int
     * @throws DelivererAgripException
     */
    private function getQuantityPages(Crawler $crawlerFirstPage): int
    {
        $quantityPages = 1;
        $crawlerFirstPage->filter('#pagination ul.pagination li a')
            ->each(function (Crawler $aElement) use (&$quantityPages) {
                $text = $this->getTextCrawler($aElement);
                $number = (int)$text;
                if ($number > $quantityPages) {
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
     * @throws DelivererAgripException
     */
    private function getStock(Crawler $containerProduct): int
    {
        $td = $this->getTdElement('<i class="fa fa-cubes"></i>', $containerProduct);
        if (!$td) {
            return 0;
        }
        $text = $this->getTextCrawler($td);
        $explodeText = explode(' ', $text);
        $stock = $explodeText[0];
        return (int)$stock;
    }

    /**
     * Get unit
     *
     * @param Crawler $containerProduct
     * @return string
     * @throws DelivererAgripException
     */
    private function getUnit(Crawler $containerProduct): string
    {
        $td = $this->getTdElement('<i class="fa fa-cubes"></i>', $containerProduct);
        if (!$td) {
            return '';
        }
        $text = $this->getTextCrawler($td);
        $explodeText = explode(' ', $text);
        return $explodeText[1];
    }

    /**
     * Get ID product
     *
     * @param Crawler $containerProduct
     * @return string|null
     * @throws DelivererAgripException
     */
    private function getIdProduct(Crawler $containerProduct): ?string
    {
        $exists = $containerProduct->filter('h5 a')->count() > 0;
        if (!$exists) {
            return null;
        }
        $href = $this->getAttributeCrawler($containerProduct->filter('h5 a'), 'href');
        $id = explode('product/product/', $href)[1] ?? '';
        $id = explode('/', $id)[0];
        $id = (int)$id;
        if (!$id) {
            throw new DelivererAgripException('Not found ID product.');
        }
        return (string)$id;
    }

    /**
     * Get TD element
     *
     * @param string $contains
     * @param Crawler $containerProduct
     * @return Crawler|null
     * @throws DelivererAgripException
     */
    private function getTdElement(string $contains, Crawler $containerProduct): ?Crawler
    {
        $tdElement = null;
        $containerProduct->filter('td')
            ->each(function (Crawler $td) use (&$tdElement, &$contains) {
                $html = $td->outerHtml();
                if (Str::contains($html, $contains)) {
                    $tdElement = $td;
                }
            });
        if (!$tdElement) {
            return null;
        }
        return $tdElement;
    }


}
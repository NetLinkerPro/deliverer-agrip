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
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\EkspertWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class EkspertListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor;

    /** @var EkspertWebsiteClient $websiteClient */
    protected $websiteClient;

    /**
     * SoapListProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(EkspertWebsiteClient::class, [
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
        $crawlerFirstPage = $this->getCrawlerPage(1);;
        $quantityPages = $this->getQuantityPages($crawlerFirstPage);
        for ($page = 1; $page <= $quantityPages; $page++) {
            $crawlerPage = $page ===1 ? $crawlerFirstPage : $this->getCrawlerPage($page);
            $products = $this->getProductsCrawlerPage($crawlerPage);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    /**
     * Get product
     *
     * @param Crawler $containerProduct
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function getProduct(Crawler $containerProduct): ?ProductSource
    {
        $id = $this->getIdProduct($containerProduct);
        DelivererLogger::log(sprintf('Product ID: %s.', $id));
        $price = $this->getPrice($containerProduct);
        if (!$price) {
            return null;
        }
        $stock = $this->getStock($containerProduct);
        $availability = 1;
        $url = sprintf('https://b2b.agrip.pl/offer/show/category/0/id/%s',$id);
        $product = new ProductSource($id, $url);
        $product->setProperty('unit', $this->getUnit($containerProduct));
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setAvailability($availability);
        return $product;
    }

    /**
     * Get content page
     *
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(int $page): Crawler
    {
        DelivererLogger::log(sprintf('Get data page %s.', $page));
        $url = sprintf('https://b2b.agrip.pl/offer/index/page/%s?controller=offer&action=index&module=default&search=&field%%5B0%%5D=wszystko&producer=&quantity=s&category=', $page);
        $contents = $this->websiteClient->getContents($url);
        return $this->getCrawler($contents);
    }

    /**
     * Get products data page
     *
     * @param Crawler $crawlerPage
     * @return array
     */
    private function getProductsCrawlerPage(Crawler $crawlerPage): array
    {
        $products = [];
        $crawlerPage->filter('section.offers article.offer')->each(function (Crawler $containerProduct) use (&$products) {
            $product = $this->getProduct($containerProduct);
            if ($product) {
                array_push($products, $product);
            }
        });
        return $products;
    }

    /**
     * Get data price
     *
     * @param Crawler $containerProduct
     * @return float
     * @throws DelivererAgripException
     */
    private function getPrice(Crawler $containerProduct): float
    {
        $id = $this->getIdProduct($containerProduct);
        $price = null;
        $containerProduct->filter('div.item-price span')
            ->each(function (Crawler $span)use(&$price, &$id){
                $idAttr = $this->getAttributeCrawler($span, 'id');
                if (!$price && Str::contains($idAttr, sprintf('price-%s', $id))){
                    $text = $this->getTextCrawler($span);
                    $text = str_replace([' ', ';&nbsp;', 'PLN', ','], '', $text);
                    $text = str_replace(',', '.',$text);
                    $price = $this->extractFloat($text);
                }
            });
        if ($price === null){
            throw new DelivererAgripException('Not found price.');
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
        $crawlerFirstPage->filter('#pager-top ul.pager-nav li span')
            ->each(function(Crawler $span) use (&$quantityPages){
                $text = $this->getTextCrawler($span);
                $number = (int) $text;
                if ($number > $quantityPages){
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
        return (int) $this->getTextCrawler($containerProduct->filter('div.quantity span.number'));
    }

    /**
     * Get unit
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getUnit(Crawler $containerProduct): string
    {
        $text = $this->getTextCrawler($containerProduct->filter('div.jm span.value'));
        if ($text === 'szt'){
            $text .= '.';
        }
        return str_replace([' ', ';&nbsp;'], '', $text);
    }

    /**
     * Get ID product
     *
     * @param Crawler $containerProduct
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdProduct(Crawler $containerProduct): string
    {
       $id =  $this->getAttributeCrawler($containerProduct, 'data-id');
        if (!$id){
            throw new DelivererAgripException('Not found ID product.');
        }
        return $id;
    }


}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\PhpListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class PhpListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var PhpWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var PhpListCategories $listCategories */
    protected $listCategories;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(PhpWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(PhpListCategories::class, [
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
    private function getProducts(CategorySource $mainCategory): Generator
    {
        $crawlerPage = $this->getCrawlerPage($mainCategory->getUrl(), 1);
        $pages = $this->getPages($crawlerPage);
        foreach (range(1, $pages) as $page) {
            $crawlerPage = $page === 1 ? $crawlerPage : $this->getCrawlerPage($mainCategory->getUrl(), $page);
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
        $trElements = $containerHtmlElement->filter('table.ofit table.itp tr');
        $trElement = $trElements->eq(1);
        $tdElement = $trElement->filter('td')->eq(1);
        $textPrice = $this->getTextCrawler($tdElement);
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
        $name=  $this->getTextCrawler($containerHtmlElement->filter('table.ofit a'));
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
        $crawlerPage->filter('#group_view div.step_row2 a.step')->each(function(Crawler $aElement) use (&$pages){
            $textAElement = $this->getTextCrawler($aElement);
            $foundPages = (int) $textAElement;
            $pages = $foundPages >$pages ? $foundPages : $pages;
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
        $products = $crawlerPage->filter('div#group_view > div.item_row')
            ->each(function (Crawler $containerHtmlElement) {
                $id = $this->getId($containerHtmlElement);
                $url = $this->getUrl($containerHtmlElement);
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                if (!$id) {
                    return null;
                }
                $product = new ProductSource($id, $url);
                $product->setProperty('long_name', $this->getName($containerHtmlElement));
                $product->setProperty('unit', $this->getUnit($containerHtmlElement));
                $product->setAvailability(1);
                $product->setTax(23);
                $product->setPrice($price);
                $product->setStock($stock);
                $this->setMinimumQuantity($product, $containerHtmlElement);
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
        $href = $this->getAttributeCrawler($containerHtmlElement->filter('table.ofit a'), 'href');
        return sprintf('https://www.agrip.pl%s',$href);
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
        $href = $this->getAttributeCrawler($containerHtmlElement->filter('table.ofit a'), 'href');
        $id = explode('-', $href)[0];
        $id = str_replace('/', '', $id);
        $idInteger = (int)$id;
        if (!$idInteger){
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
        $containerHtmlElement->filter('table.ofit div.item_section')->each(function(Crawler $itemElement) use (&$stock){
           $textElement = $this->getTextCrawler($itemElement);
           if (Str::contains($textElement, 'Stan:')){
               $stock = $this->extractInteger($textElement);
           }
        });
        return $stock;
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
        if (!Str::endsWith($urlCategory, '/')){
            $urlCategory .= '/';
        }
        $url = sprintf('%s%s/', $urlCategory, $page);
        $content = $this->websiteClient->getContent($url);
        $html = str_replace(['<!--', '-->'], '', $content);
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

}
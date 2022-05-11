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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MistralWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SymfonyWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class MistralListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor;

    const PER_PAGE = '10';

    /** @var MistralWebsiteClient $websiteClient */
    protected $websiteClient;

    /**
     * SoapListProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(MistralWebsiteClient::class, [
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
            foreach ($products as $product) {
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
        $id = $this->getAttributeCrawler($containerProduct->filter('td.tbxCheckBox input'), 'value');
        $price = $this->getPrice($containerProduct);
        if (!$price) {
            return null;
        }
        $stock = $this->getStock($containerProduct);
        $tax = 23;
        $availability = 1;
        $url = sprintf('https://www.hurt.aw-narzedzia.com.pl/%s', $this->getAttributeCrawler($containerProduct->filter('td')->eq(2)->filter('a'), 'href'));
        $product = new ProductSource($id, $url);
        $product->setCategories([$category]);
        $product->setPrice($price);
        $product->setTax($tax);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $product->setTax(23);
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
        $viewstateKey = $this->websiteClient->getLastViewstateKey();
        return $this->websiteClient->getContents('https://www.hurt.aw-narzedzia.com.pl/ProduktyWyszukiwanie.aspx?search=', [
            '_' => [
                'method' => 'POST'
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' => 'ctl00$MainContent$mkKategorie$mtKategorieWielopoziomowe',
                '__EVENTARGUMENT' => sprintf('%s_Dłutownice', $idCategory),
                //'__VIEWSTATE_KEY' => $viewstateKey,
                '__VIEWSTATE' => '',
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '265',
                'ctl00_miWyszukiwanieProduktow' => '',
                'ctl00_miWyszukiwanieProduktow_encoded' => '',
                'widok_kategorii' => 'ctWidokKategorieWielopoziomowe',
                'id_kategorii_glownych' => 'ctl00$MainContent$mkKategorie$mtKategorie',
                'id_kategorii_wielopoz' => 'ctl00$MainContent$mkKategorie$mtKategorieWielopoziomowe',
                'ctWidokKategorieGlowneSelect' => 'undefined_ALL',
                'widok_producentow' => 'ctWidokKategorieWielopoziomowe',
                'ctl00_MainContent_mtProduktyWyszukane$currentPage' => $page,
                'ctl00_MainContent_mtProduktyWyszukane$pageSize' => self::PER_PAGE,
                'ctl00_MainContent_mtProduktyWyszukane$pageSize1' => self::PER_PAGE,
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
        $crawlerPage->filter('div.TableWrapperContent > table tbody tr')->each(function (Crawler $containerProduct) use (&$products, &$category) {
            $product = $this->getProduct($containerProduct, $category);
            if ($product) {
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
        $text = $this->getTextCrawler($containerProduct->filter('td')->eq(7));
        $text = str_replace([' ', ';&nbsp;', 'PLN'], '', $text);
        $text = str_replace(',', '.', $text);
        return $this->extractFloat($text);
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
        $text = $this->getTextCrawler($crawlerFirstPage->filter('#srodkowoPrawaKolumna h2.naglowek i'));
        if (Str::contains($text, ' z ')) {
            $text = explode(' z ', $text)[1];
            $text = explode(' wysz', $text)[0];
            $quantityPages = (int)ceil(((int)$text) / self::PER_PAGE);
        }
        if (!$quantityPages) {
            throw new DelivererAgripException('Incorrect quantity pages.');
        }
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
        $text = $this->getAttributeCrawler($containerProduct->filter('td')->eq(5)->filter('span'), 'class');
        if ($text === 'stanDuzo') {
            return 20;
        } else if ($text === 'stanSrednio') {
            return 10;
        } else if ($text === 'stanMalo') {
            return 3;
        } else if ($text === 'stanBrak') {
            return 0;
        }
        throw new DelivererAgripException('Not detect stock.');
    }

    /**
     * Get unit
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getUnit(Crawler $containerProduct): string
    {
        $text = $this->getAttributeCrawler($containerProduct->filter('td')->eq(8)->filter('span'), 'class');
        return str_replace([' ', ';&nbsp;'], '', $text);
    }

}
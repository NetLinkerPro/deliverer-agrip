<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\PhpListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NginxWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class NginxListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var NginxWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var array $bellaProducts */
    protected $bellaProducts;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(NginxWebsiteClient::class, [
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
        $this->initializeBellaProducts();
        $products = $this->getProducts();
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get products
     *
     * @return Generator
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getProducts(): Generator
    {
        $crawlerPage = $this->getCrawlerPage(1);
        $pages = $this->getPages($crawlerPage);
        foreach (range(1, $pages) as $page) {
            $crawlerPage = $page === 1 ? $crawlerPage : $this->getCrawlerPage($page);
            $products = $this->getProductsPage($crawlerPage);
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
        $tdElement = $containerHtmlElement->filter('td.col-cena');
        $textPrice = $this->getTextCrawler($tdElement);
        $textPrice = str_replace([' ', ',', '&nbsp;', 'zł', 'z'], '', $textPrice);
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
        $crawlerPage->filter('#katalog1 a')->each(function (Crawler $aElement) use (&$pages) {
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
    private function getProductsPage(Crawler $crawlerPage): array
    {
        $products = $crawlerPage->filter('#aktualizuj_koszyk table table tr')
            ->each(function (Crawler $containerHtmlElement) {
                $bellaProduct = $this->getBellaProduct($containerHtmlElement);
                if (!$bellaProduct) {
                    return null;
                }
                $id = $this->getId($containerHtmlElement);
                if (!$id) {
                    return null;
                }
                $url = $this->getUrl($containerHtmlElement);
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                $product = new ProductSource($id, $url);
                $product->setAvailability(1);
                $tax = $bellaProduct['tax'] ?? 23;
                $product->setTax($tax);
                $product->setPrice($price);
                $product->setStock($stock);
                $product->addCategory($bellaProduct['category']);
                $product->setProperty('EAN', $bellaProduct['ean']);
                $product->setProperty('bella_image',$bellaProduct['image_url']);
                return $product;
            });
        return array_filter($products);
    }

    /**
     * Get URL
     *
     * @param Crawler $containerHtmlElement
     * @return string
     * @throws DelivererAgripException
     */
    private function getUrl(Crawler $containerHtmlElement): string
    {
        $id = $this->getId($containerHtmlElement);
        return sprintf('https://b2b.agrip.pl/karta-produktu.html?twid=%s', $id);
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
        $href = $this->getAttributeCrawler($containerHtmlElement->filter('td.col-nazwa a'), 'href');
        $id = explode('(', $href)[1];
        $id = str_replace(');', '', $id);
        $idInteger = (int)$id;
        if (!$idInteger) {
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
        $textAttribute = $this->getAttributeCrawler($containerHtmlElement->filter('td.col-ilosc input'), 'onchange');
        if (!Str::contains($textAttribute, 'sprawdz_stan')) {
            throw new DelivererAgripException('Incorrect get stock.');
        }
        $textAttribute = explode('this.name,', $textAttribute)[1] ?? '';
        $textAttribute = explode(',', $textAttribute)[0];
        return (int)$textAttribute;
    }

    /**
     * Get crawler page
     *
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getCrawlerPage(int $page): Crawler
    {
        $url = 'https://b2b.agrip.pl/katalog.html';
        $content = $this->websiteClient->postContent($url, [
            RequestOptions::FORM_PARAMS => [
                'dostepne' => 1,
                'nowosci' => 0,
                'gazetka' => 0,
                'sekret' => 0,
                'mur' => 0,
                'opisy' => 0,
                'zakres_nazwa' => '',
                'grupaid' => '',
                'kategoriaid' => '',
                'ilew' => 200,
                'strona' => $page,
            ]
        ]);
        $html = str_replace(['<!--', '-->'], '', $content);
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        return $this->getCrawler($html);
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
     * Get Bella product
     *
     * @param Crawler $containerHtmlElement
     * @return array|null
     */
    private function getBellaProduct(Crawler $containerHtmlElement): ?array
    {
        $name = $this->getTextCrawler($containerHtmlElement->filter('td.col-nazwa a'));
        if (!$name) {
            return null;
        }
        $eanExplode = explode('(', $name);
        if (sizeof($eanExplode) < 2) {
            return null;
        }
        $ean = $eanExplode[sizeof($eanExplode) - 1];
        $eanExplode = explode(')', $ean);
        $ean = $eanExplode[0];
        if ($ean && $this->isValidEan($ean)) {
            return $this->bellaProducts[$ean] ?? null;
        } else {
            return null;
        }
    }

    /**
     * Initialize Bella products
     *
     * @throws DelivererAgripException
     */
    private function initializeBellaProducts(): void
    {
        $file = __DIR__ . '/../../../../../resources/data/4bella_ean.txt';
        if (!File::exists($file)) {
            throw new DelivererAgripException('Not fount Bella file products.');
        }
        $content = File::get($file);
        $this->bellaProducts = unserialize($content);
    }

    /**
     * Is Valid EAN
     *
     * @param $barcode
     * @return bool
     */
    private function isValidEan($barcode): bool
    {
        $barcode = (string)$barcode;
        if (!preg_match("/^[0-9]+$/", $barcode)) {
            return false;
        }
        $l = strlen($barcode);
        if (!in_array($l, [8, 12, 13, 14, 17, 18]))
            return false;
        $check = substr($barcode, -1);
        $barcode = substr($barcode, 0, -1);
        $sum_even = $sum_odd = 0;
        $even = true;
        while (strlen($barcode) > 0) {
            $digit = substr($barcode, -1);
            if ($even)
                $sum_even += 3 * $digit;
            else
                $sum_odd += $digit;
            $even = !$even;
            $barcode = substr($barcode, 0, -1);
        }
        $sum = $sum_even + $sum_odd;
        $sum_rounded_up = ceil($sum / 10) * 10;
        return ($check == ($sum_rounded_up - $sum));
    }

}
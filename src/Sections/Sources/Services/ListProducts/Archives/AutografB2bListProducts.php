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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AutografB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NginxWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class AutografB2bListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var AutografB2bWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(AutografB2bWebsiteClient::class, [
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
        $divElement = $containerHtmlElement->filter('div.price-inline.pi1');
        $textPrice = $this->getTextCrawler($divElement);
        $textPrice = str_replace(',', '.', $textPrice);
        $textPrice = str_replace([' ', '&nbsp;', 'zł', 'PLN'], '', $textPrice);
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
     * @throws DelivererAgripException
     */
    private function getPages(Crawler $crawlerPage): int
    {
        $text = $this->getTextCrawler($crawlerPage->filter('extension'));
        $text = explode('{"totalRecords":', $text)[1] ?? '';
        $text = explode('}', $text)[0];
        $records = (int) $text;
        if (!$records){
            throw new DelivererAgripException('Not found records.');
        }
        return ceil($records / 15);
    }

    /**
     * Get products category
     *
     * @param Crawler $crawlerPage
     * @return array
     */
    private function getProductsPage(Crawler $crawlerPage): array
    {
        $products = $crawlerPage->filter('#productForm-dgProducts > div')
            ->each(function (Crawler $containerHtmlElement) {
                $id = $this->getId($containerHtmlElement);
                if (!$id) {
                    return null;
                }
                $url = $this->getUrl($containerHtmlElement);
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                $product = new ProductSource($id, $url);
                $product->setAvailability(1);
                $tax =23;
                $product->setTax($tax);
                $product->setPrice($price);
                $product->setStock($stock);
                return $product;
            });
        $products = array_filter($products);
        $productsWithPins = [];
        /** @var ProductSource $product */
        foreach ($products as $product) {
            array_push($productsWithPins, $product);
            $product7pin = clone $product;
            $product7pin->setId(sprintf('%s__7', $product7pin->getId()));
            array_push($productsWithPins, $product7pin);
            $product13pin = clone $product;
            $product13pin->setId(sprintf('%s__13', $product13pin->getId()));
            array_push($productsWithPins, $product13pin);
        }
        return $productsWithPins;
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
        return 'http://b2b.agrip.pl/e-zamowienia-www/app/produkty/0';
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
        $id = $this->getTextCrawler($containerHtmlElement->filter('span.article-index-simple'));
        $id = Str::slug($id);
        if (!$id) {
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
      $spanActives = $containerHtmlElement->filter('.simple-storage-column .stock-state-wrapper span.stock_state_active')->count();
        if ($spanActives === 4){
            return 20;
        }else if ($spanActives ===3){
            return 10;
        } else if ($spanActives ===2){
            return 5;
        } else if ($spanActives === 1){
            return 2;
        } else {
            return 0;
        }
    }

    /**
     * Get crawler page
     *
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(int $page): Crawler
    {
        $limit = 15;
        $offset = ($page - 1) * $limit;
        $url = 'http://b2b.agrip.pl/e-zamowienia-www/app/produkty/0';
        $content = $this->websiteClient->getContentAjax($url, [
            RequestOptions::FORM_PARAMS => $this->buildFormParams($offset, $limit),
            '_log_suffix' => sprintf(' %s', $page),
        ]);
        $html = str_replace(['<!--', '-->'], '', $content);
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        return $this->getCrawler($html);
    }

    /**
     * Build form params
     *
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws DelivererAgripException
     */
    private function buildFormParams(int $offset, int $limit): array
    {
        return [
            'javax.faces.partial.ajax' => 'true',
            'javax.faces.source' => 'productForm-dgProducts',
            'javax.faces.partial.execute' => 'productForm-dgProducts',
            'javax.faces.partial.render' => 'productForm-dgProducts',
            'javax.faces.behavior.event' => 'page',
            'javax.faces.partial.event' => 'page',
            'productForm-dgProducts_pagination' => 'true',
            'productForm-dgProducts_first' =>$offset,
            'productForm-dgProducts_rows' => $limit,
            'productForm' => 'productForm',
            'productForm-j_idt336_focus' => '',
            'productForm-j_idt336_input' => 'i18nname_asc',
            'productForm-tbProducts_activeIndex' => 1,
            'productForm-dgProducts_rppDD' => $limit,
            'javax.faces.ViewState' => $this->websiteClient->getViewState(),
        ];
    }

}
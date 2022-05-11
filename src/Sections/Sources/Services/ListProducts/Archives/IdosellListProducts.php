<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\IdosellListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\IdosellWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\WideStore\Sections\Products\Models\Product;
use Symfony\Component\DomCrawler\Crawler;

class IdosellListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var IdosellWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var IdosellListCategories */
    protected $listCategories;

    /** @var string $fromAddProduct */
    protected $fromAddProduct;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password, string $fromAddProduct = null)
    {
        $this->websiteClient = app(IdosellWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(IdosellListCategories::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->fromAddProduct = $fromAddProduct;
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
     * Get live
     *
     * @return Generator|ProductSource[]|array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getLive(): Generator
    {
        $products = Product::where('deliverer', 'agrip')
            ->cursor();
        foreach ($products as $product){
            $id = $product->identifier;
            $url = sprintf('https://agrip.pl/product-pol-%s', $id);
            $product = new ProductSource($id, $url);
            $this->fillLiveProduct($product);
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
        $listCategories = $this->listCategories->get();
        /** @var CategorySource $category */
        $fromIdCategory = explode(';', $this->fromAddProduct)[0]??null;
        foreach ($listCategories as $category) {
            if ($category->getId() === $fromIdCategory){
                $fromIdCategory = null;
            }
            if ($fromIdCategory){
                continue;
            }
            $crawlerPage = $this->getCrawlerPage($category, 1);
            $pages = $this->getPages($crawlerPage);
            foreach (range(1, $pages) as $page) {
                $crawlerPage = $page === 1 ? $crawlerPage : $this->getCrawlerPage($category, $page);
                $products = $this->getProductsPage($crawlerPage);
                foreach ($products as $product) {
                    yield $product;
                }
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
        $divElement = $containerHtmlElement->filter('div.product_prices span.price');
        $textPrice = $this->getTextCrawler($divElement);
        $textPrice = explode('/', $textPrice)[0];
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
        $text = $this->getTextCrawler($crawlerPage->filter('a.pagination_last'));
        $pages = (int)$text;
        if (!$pages) {
            $pages = 1;
        }
        return $pages;
    }

    /**
     * Get products category
     *
     * @param Crawler $crawlerPage
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsPage(Crawler $crawlerPage): array
    {
        $products = $crawlerPage->filter('#content .product_wrapper')
            ->each(function (Crawler $containerHtmlElement) {
                $id = $this->getId($containerHtmlElement);
                if (!$id) {
                    return null;
                }
                $delivery = $this->getDelivery($containerHtmlElement);
                if (in_array($delivery, ['dni', '5 dni', '6 dni', '7 dni', '22 dni'])){
                    return null;
                }
                $url = $this->getUrl($containerHtmlElement);
//                $price = $this->getPrice($containerHtmlElement);
//                $stock = $this->getStock($containerHtmlElement);
                $product = new ProductSource($id, $url);
                $this->fillLiveProduct($product);
                return $product;
            });
        return array_filter($products);
    }

    /**
     * Get live product
     *
     * @param ProductSource $product
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function fillLiveProduct(ProductSource $product): ?ProductSource
    {
        usleep(200000);
        $contents = $this->websiteClient->getContentAjax(sprintf('https://agrip.pl/ajax/projector.php?action=get&product=%s&get=sizes', $product->getId()), [], 'GET', '{"sizes":{"id":');
        $data = json_decode($contents, true);
        $tax = (int)($data['sizes']['taxes']['vat'] ?? '');
        $price = (float) ($data['sizes']['items']['uniw']['prices']['price_wholesale'] ?? '');
        $quantity = (int) ($data['sizes']['items']['uniw']['amount'] ?? '');
        $product->setAvailability(1);
        if ($tax){
            $product->setTax($tax);
        }
        if ($price){
            $product->setPrice($price);
        }
        $product->setStock($quantity);
        return $product;
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
        return sprintf('https://agrip.pl/product-pol-%s', $id);
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
        $id = $this->getAttributeCrawler($containerHtmlElement, 'data-product_id');
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
        if ($spanActives === 4) {
            return 20;
        } else if ($spanActives === 3) {
            return 10;
        } else if ($spanActives === 2) {
            return 5;
        } else if ($spanActives === 1) {
            return 2;
        } else {
            return 0;
        }
    }

    /**
     * Get crawler page
     *
     * @param CategorySource $category
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(CategorySource $category, int $page): Crawler
    {
        $counter = $page - 1;
        $url = sprintf('%s?counter=%s', $category->getUrl(), $counter);
        $content = $this->websiteClient->getContentAjax($url, [
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
            'productForm-dgProducts_first' => $offset,
            'productForm-dgProducts_rows' => $limit,
            'productForm' => 'productForm',
            'productForm-j_idt336_focus' => '',
            'productForm-j_idt336_input' => 'i18nname_asc',
            'productForm-tbProducts_activeIndex' => 1,
            'productForm-dgProducts_rppDD' => $limit,
            'javax.faces.ViewState' => $this->websiteClient->getViewState(),
        ];
    }

    /**
     * Get delivery
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getDelivery(Crawler $containerHtmlElement): string
    {
        $textDelivery = $this->getTextCrawler($containerHtmlElement->filter('div.product-delivery b'));
        return mb_strtolower($textDelivery);
    }

}
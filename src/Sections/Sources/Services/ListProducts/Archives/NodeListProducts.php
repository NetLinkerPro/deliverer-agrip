<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\NodeListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NodeWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\WideStore\Sections\Products\Models\Product;
use Symfony\Component\DomCrawler\Crawler;

class NodeListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var NodeWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var NodeListCategories */
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
        $this->websiteClient = app(NodeWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(NodeListCategories::class, [
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
        $fromIdCategory = explode(';', $this->fromAddProduct)[0] ?? null;
        foreach ($listCategories as $category) {
            if ($category->getId() === $fromIdCategory) {
                $fromIdCategory = null;
            }
            if ($fromIdCategory) {
                continue;
            }
            $crawlerPage = $this->getCrawlerPage($category, 1);
            $pages = $this->getPages($crawlerPage);
            foreach (range(1, $pages) as $page) {
                $crawlerPage = $page === 1 ? $crawlerPage : $this->getCrawlerPage($category, $page);
                $products = $this->getProductsPage($crawlerPage, $category);
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
        return $this->extractFloat($this->getAttributeCrawler($containerHtmlElement->filter('div.price_stock .price_source'), 'data-price'));
    }

    /**
     * Get name
     *
     * @param Crawler $containerHtmlElement
     * @return string
     */
    private function getName(Crawler $containerHtmlElement): string
    {
        $crawler = $containerHtmlElement->filter('.title.smartName');
        $crawler->filter('strong')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $name = $this->getTextCrawler($crawler);
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
        $text = $this->getAttributeCrawler($crawlerPage->filter('.divProductListExtraControls .divPageLinks .ajaxpagelink.last'), 'data-page');
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
     * @param CategorySource $category
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsPage(Crawler $crawlerPage, CategorySource $category): array
    {
        $products = $crawlerPage->filter('.divProuctsList > .divProductTile')
            ->each(function (Crawler $containerHtmlElement) use (&$category) {
                if (!$this->getAttributeCrawler($containerHtmlElement, 'data-search')){
                    return null;
                }
                $id = $this->getId($containerHtmlElement);
                $url = $this->getUrl($containerHtmlElement);
                $product = new ProductSource($id, $url);
                $product->setProperty('last_category', $category);
                $product->setAvailability(1);
                $name = $this->getName($containerHtmlElement);
                if (!$name){
                    return null;
                }
                $price = $this->getPrice($containerHtmlElement);
                $stock = $this->getStock($containerHtmlElement);
                $product->setName($name);
                $product->setPrice($price);
                $product->setStock($stock);
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
        return sprintf('https://www.agrip.pl/offer/pl/0/#/product/?pr=%s', $id);
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
        $id = $this->getAttributeCrawler($containerHtmlElement->filter('.price_stock .price_source'), 'data-id');
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
        return $this->extractInteger($this->getTextCrawler($containerHtmlElement->filter('div.price_stock .instock')));
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
        $contents = $this->websiteClient->getContentAjax('https://www.agrip.pl/index.php', [
            '_log_suffix' => sprintf(' %s', $page),
            RequestOptions::FORM_PARAMS=>[
                'products_action' => 'ajax_category',
                'is_ajax' => '1',
                'ajax_type' => 'json',
                'url' => sprintf('https://www.agrip.pl/offer/pl/_/#/list/?gr=%s&p=%s&pmin_stock=1&e=0', $category->getId(), $page),
                'locale_ajax_lang' => 'pl',
                'products_ajax_group' => $category->getId(),
                'products_ajax_search' => '',
                'products_ajax_page' => (string) $page,
                'products_ajax_view' => 't',
                'products_ajax_stock' => 's',
                'products_ajax_sort' => 'name',
                'products_ajax_sort_dir' => 'a',
                'products_ajax_filter' => '{"srch":"","e":"0","pmin_stock":"1"}',
                'products_ajax_filter_html' => '0',
                'products_ajax_csv_export' => '0',
                'products_ajax_use_desc_index' => '1',
            ]
        ]);
        $data = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
        $dataParts = $data[1];
        foreach ($dataParts as $dataPart){
            if ($dataPart['type'] === 'productlist_html'){
                $html = $dataPart['data'];
                $html = str_replace(['<!--', '-->'], '', $html);
                $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
                return $this->getCrawler($html);
            }
        }
        throw new DelivererAgripException('Bad response with list products.');
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
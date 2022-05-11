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
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\InsolutionsListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\EhurtowniaWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\InsolutionsWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class EhurtowniaListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var EhurtowniaWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(EhurtowniaWebsiteClient::class, [
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
        $products = $this->getProductsStorage(1);
        foreach ($products as $product){
            yield $product;
        }
        $products = $this->getProductsStorage(2);
        foreach ($products as $product){
            yield $product;
        }
    }

    /**
     * Get from resource
     *
     * @return mixed
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getFromResourceRemember(){
        $pathResource = __DIR__ . '/../../../../../resources/data/list_products.data';
        return $this->resourceRemember($pathResource, 932000, function(){
            $products = $this->get();
            $resource = [];
            foreach ($products as $product){
                $resource[$product->getId()] = $product;
            }
            return $resource;
        });
    }

    /**
     * Get products
     *
     * @param array $dataPage
     * @return Generator
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getProducts(array $dataPage): Generator
    {
        $positions = $dataPage['pozycje'];
        foreach ($positions as $position) {
            $product = new ProductSource($position['indeks'],'https://agrip.ehurtownia.pl');
            $image = $position['zdjecie'] ?? '';
            $product->setProperty('image', $image);
            $product->setName($position['nazwa']);
            yield $product;
        }
    }

    /**
     * Get data page
     *
     * @param int $page
     * @param int $storage
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDataPage(int $page, int $storage): array
    {
        $offset = $page <= 1 ? 0 : ($page -1) * 50;
        $url = sprintf('https://agrip.ehurtownia.pl/eh-one-backend/rest/47/%s/2135870/oferta?lang=PL&offset=%s&limit=50&sortAsc=indeks', $storage, $offset);
        $content = $this->websiteClient->getContentAjax($url, [], 'GET');
        return json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get pages
     *
     * @param array $dataPage
     * @return int
     */
    private function getPages(array $dataPage): int
    {
        return ceil($dataPage['countPozycjiOferty'] / 50);
    }

    /**
     * Get products storage
     *
     * @param int $storage
     * @return Generator|ProductSource[]
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getProductsStorage(int $storage): Generator
    {
        $dataPageFirst = $this->getDataPage(1, $storage);
        $pages = $this->getPages($dataPageFirst);
        foreach (range(1, $pages) as $page){
            $dataPage = $page === 1 ? $dataPageFirst : $this->getDataPage($page, $storage);
            $products = $this->getProducts($dataPage);
            foreach ($products as $product){
                yield $product;
            }
        }
    }

}
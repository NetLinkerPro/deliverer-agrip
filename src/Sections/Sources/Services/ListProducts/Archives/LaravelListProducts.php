<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\LaravelWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class LaravelListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor;

    /** @var LaravelWebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var array $priceStock */
    protected $priceStock;

    /** @var int $countToSave */
    protected $countToSave;

    /** @var array $throttleStock */
    protected $throttleStock;

    /**
     * SoapListProducts constructor
     *
     * @param string $login
     * @param string $password
     * @param string $login2
     */
    public function __construct(string $login, string $password, string $login2)
    {
        $this->websiteClient = app(LaravelWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
            'login2' =>$login2,
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
        $this->loadPriceStock();
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
        $url = sprintf('https://b2b.agrip.pl/product/%s', $id);
        $product = new ProductSource($id, $url);
        $this->setUnits($containerProduct,$product);
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $product->setCategories([$category]);
        $name = $this->getName($product, $containerProduct);
        $product->setName($name);
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
        DelivererLogger::log(sprintf('Get data page %s.', $page));
        $url = sprintf('https://b2b.agrip.pl/group/%s?code=&name=&stockAvailable=on&sort=name%%2Basc&page=%s', $idCategory, $page);
        $contents = $this->websiteClient->getContents($url);
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
        $crawlerPage->filter('table.table tr.show-details')->each(function (Crawler $containerProduct) use (&$products, &$category) {
            $tds = $containerProduct->filter('td');
            if ($tds->count() > 2) {
                $product = $this->getProduct($containerProduct, $category);
                if ($product) {
                    if ($product->getProperty('rolka')){
                        $productRolka = $this->getProductRolka($product->clone());
                        if ($productRolka){
                            array_push($products, $productRolka);
                        }
                    } else if ($product->getProperty('in_pack')){
                        $productWithInPack = $this->getProductWithInPack($product->clone());
                        if ($productWithInPack){
                            array_push($products, $productWithInPack);
                        }
                    }
                    $product->addAttribute('Ilość', sprintf('1 %s', $product->getProperty('unit')), 120);
                    array_push($products, $product);
//                    $product = $this->correctProductMeters($product);
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
        $text = $this->getTextCrawler($containerProduct->filter('td')->eq(3));
        if (!Str::contains($text, 'PLN')){
            throw new DelivererAgripException('Price not contains "PLN" text.');
        }
        $text = str_replace([' ', ';&nbsp;', 'PLN', '.'], '', $text);
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
        $crawlerFirstPage->filter('ul.pagination li a')
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
        $text = $this->getTextCrawler($containerProduct->filter('td')->eq(2));
        $html = $containerProduct->filter('td')->eq(2)->html();
        if (!Str::contains($html, 'fa-circle')){
            throw new DelivererAgripException('Stock not contains "fa-circle" text.');
        }
        if ($text === 'Dostępny'){
            $sku = $this->getSku($containerProduct);
            $stockWezeizlacza = $this->getStockWezeizlacza($sku);
            $stockWezeizlacza -= 10;
            if ($stockWezeizlacza > 2){
                return $stockWezeizlacza;
            }
            return 1;
        }
        return 0;
    }

    /**
     * Set units
     *
     * @param Crawler $containerProduct
     * @param ProductSource $product
     * @return string
     * @throws DelivererAgripException
     */
    private function setUnits(Crawler $containerProduct, ProductSource $product): void
    {
        $id = $this->getIdProduct($containerProduct);
        $options = $containerProduct->filter(sprintf('#unitSelect_%s option', $id));
        $option1 = $containerProduct->filter(sprintf('#unitSelect_%s option', $id))->eq(0);
        $unit1 = $this->getTextCrawler($option1);
        $ratio1 = (int) $this->getAttributeCrawler($option1, 'data-ratio');
        if ($ratio1 > 1){
            throw new DelivererAgripException('Radio is greater than 1.');
        }
        $product->setProperty('unit', sprintf('%s', $unit1));
        if ($options->count() > 1){
            $option2 = $containerProduct->filter(sprintf('#unitSelect_%s option', $id))->eq(1);
            $unit2 = $this->getTextCrawler($option2);
            $ratio2 = (int) $this->getAttributeCrawler($option2, 'data-ratio');
            if ($unit2 === 'rolka' && $unit1 === 'm'){
                $product->setProperty('rolka', [
                    'ratio' =>$ratio2,
                ]);
            }
        }
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
        $id = $this->getAttributeCrawler($containerProduct->filter('input[name="product_id"]'), 'value');
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

    /**
     * Get product rolka
     *
     * @param ProductSource $product
     * @return ProductSource|null
     */
    private function getProductRolka(ProductSource $product): ?ProductSource
    {
        $ratio = (int) $product->getProperty('rolka')['ratio'];
        if ($ratio < 2){
            return null;
        }
        $product->setId(sprintf('%s__%s', $product->getId(), $ratio));
        $product->setPrice(round($product->getPrice() * $ratio,2));
        $product->setStock((int) ($product->getStock() / $ratio));
        $product->setName(sprintf('%s /%sm rolka', $product->getName(), $ratio));
        $product->addAttribute('Ilość', sprintf('%s m', $ratio), 120);
        $product->setProperty('unit', 'rolka');
        return $product;
    }


    /**
     * Get product with in pack
     *
     * @param ProductSource $product
     * @return ProductSource|null
     */
    private function getProductWithInPack(ProductSource $product): ?ProductSource
    {
        $inPack = (int) $product->getProperty('in_pack');
        if ($inPack < 2){
            return null;
        }
        $product->setId(sprintf('%s__%s', $product->getId(), $inPack));
        $product->setPrice(round($product->getPrice() * $inPack,2));
        $product->setStock((int) ($product->getStock() / $inPack));
        $product->setName(sprintf('%s /%s%s', $product->getName(), $inPack, $product->getProperty('unit')));
        $product->addAttribute('Ilość', sprintf('%s %s', $inPack, $product->getProperty('unit')), 120);
        return $product;
    }

    /**
     * Correct product meters
     *
     * @param ProductSource $product
     * @return ProductSource
     */
    private function correctProductMeters(ProductSource $product): ProductSource
    {
        $unit = $product->getProperty('unit');
        throw new DelivererAgripException('Not implemented');
        return $product;
    }

    /**
     * Get name
     *
     * @param ProductSource $product
     * @param Crawler $containerProduct
     * @return string
     */
    private function getName(ProductSource $product, Crawler $containerProduct): string
    {
        $name = $this->getTextCrawler($containerProduct->filter('td')->eq(1));
        preg_match("/\((.*?)\)/",$name,$m);
        if (sizeof($m)){
            $find = $m[0];
            if (Str::contains($find, sprintf('%s)', $product->getProperty('unit')))){
                $inPackFind = $this->extractInteger($find);
                if ($inPackFind){
                    $product->setProperty('in_pack', $inPackFind);
                    $name = str_replace($find, '', $name);
                    $name = trim(preg_replace('/\s+/', ' ', $name));
                }
            }
        }
        if ($rolka = $product->getProperty('rolka')){
            $ratio = $rolka['ratio'];
            $unit = $product->getProperty('unit');
            $text = sprintf('(%s%s)', $ratio, $unit);
            $name = str_replace($text, '', $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));
        }
        return str_replace(', ', ' ', $name);
    }

    /**
     * Load price stock
     */
    private function loadPriceStock(): void
    {
        $cacheKey = sprintf('%s_price_stock', get_class($this));
        $this->priceStock = Cache::get($cacheKey,[]);
    }

    /**
     * Save price stock
     */
    private function savePriceStock(): void
    {
        if (!$this->countToSave){
            $this->countToSave = 0;
        }
        $this->countToSave++;
        if ($this->countToSave > 10){
            $cacheKey = sprintf('%s_price_stock', get_class($this));
            Cache::put($cacheKey, $this->priceStock, 10000);
            $this->countToSave = 0;
        }
    }

    /**
     * Get stock wezeizlaczki
     *
     * @param string $sku
     * @return int
     */
    public function getStockWezeizlacza(string $sku): int
    {
        $jsonData = $this->getJsonDataPriceStock($sku);
        if (!$jsonData){
            return 0;
        }
        return $jsonData['stock'];
    }

    /**
     * Get SKU
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getSku(Crawler $containerProduct): string
    {
        return $this->getTextCrawler($containerProduct->filter('td')->eq(0)->filter('strong'));
    }

    /**
     * Get JSON data price stock
     *
     * @param string $sku
     * @return array|null
     */
    private function getJsonDataPriceStock(string $sku): ?array
    {
        $priceStock = $this->priceStock[$sku] ?? null;
        if ($priceStock){
            return $priceStock;
        }
        $skuEncode = rawurlencode($sku);
        $url = sprintf('https://wezeizlacza.pl/webapi/front/pl_PL/search/short-list?text=%s&org=%s', $skuEncode, $skuEncode);
        $this->waitThrottleStock();
        DelivererLogger::log(sprintf('Get stock %s.', $url));
        $client = new Client(['verify'=>false]);
        $response = $client->get($url, [
            'headers' =>[
                'User-Agent' =>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36',
                'X-Requested-With' =>'XMLHttpRequest',
            ]
        ]);
        $contents = $response->getBody()->getContents();
        $jsonData = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
        $items = $jsonData['products']['list'] ?? [];
        foreach ($items as $item){
            $textPrice = str_replace([' ', ';&nbsp;',' ', 'PLN', '.', 'zł'], '', $item['price']);
            $textPrice = trim(str_replace(',', '.', $textPrice));
            $price = $this->extractFloat($textPrice);
            $textStock = str_replace([' ', ';&nbsp;', ' ', 'szt', 'm', 'rolka'], '', $item['attributes']['stock']);
            $textStock = str_replace(',', '.', $textStock);
            $stock = (int) $this->extractFloat($textStock);
            $this->priceStock[$item['product_code']] = [
                'price' => $price,
                'stock' =>$stock,
            ];
        }
        $this->savePriceStock();
        $priceStock =  $this->priceStock[$sku] ?? null;
        if (!$priceStock){
            DelivererLogger::log('Not found stock.');
        }
        return $priceStock;
    }

    /**
     * Wait throttle stock
     */
    private function waitThrottleStock(): void
    {
        foreach (range(1, 100) as $number){
            $data = now()->format('dHis');
            $throttleStock = $this->throttleStock[$data] ?? 0;
            if ($throttleStock > 10){
                usleep(200000);
                continue;
            }
            $throttleStock++;
            $this->throttleStock[$data] = $throttleStock;
            return;
        }
    }
}
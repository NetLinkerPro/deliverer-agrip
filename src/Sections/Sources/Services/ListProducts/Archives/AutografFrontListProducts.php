<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Exception;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\CsvFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AutografFrontWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class AutografFrontListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var AutografFrontWebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var AutografB2bListProducts $b2bListProducts */
    protected $b2bListProducts;

    /** @var array $b2bDataProducts */
    private $b2bDataProducts;

    /** @var array $eanCodes */
    private $eanCodes;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(AutografFrontWebsiteClient::class);
        $this->b2bListProducts = app(AutografB2bListProducts::class, [
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
        $this->initB2bDataProducts();
        $this->initEanCodes();
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
        $pages = 1;
        $crawlerPage->filter('div.pagination > ul > li a')->each(function (Crawler $aElement) use (&$pages) {
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
     * @throws DelivererAgripException
     */
    private function getProductsPage(Crawler $crawlerPage): array
    {
        $products = $crawlerPage->filter('div.products-list > div.product-item')
            ->each(function (Crawler $crawlerProduct) {
                $id = $this->getId($crawlerProduct);
                if (!$id) {
                    return null;
                }
                Log::debug($id);
                if ($id === 'f-064'){
                    echo "";
                }
                $product = $this->getB2bDataProduct($id);
                if (!$product) {
                    return null;
                }
                $this->addAttributesProduct($product, $crawlerProduct);
                $this->addNameProduct($product, $crawlerProduct);
                $this->addImagesProduct($product, $crawlerProduct);
                $this->addDescriptionProduct($product, $crawlerProduct);
                $this->addCategoryProduct($product);
                $product->removeLongAttributes();
                $product->check();
                return $product;
            });
        $products = array_filter($products);
        $productsWithPins = [];
        /** @var ProductSource $product */
        foreach ($products as $product) {
            $product7pin = clone $product;
            $product7pin->setId(sprintf('%s__7', $product7pin->getId()));
            $product7pin->setName(sprintf('%s - 7 pin', $product7pin->getName()));
            array_push($productsWithPins, $product7pin);
            $product13pin = clone $product;
            $product13pin->setId(sprintf('%s__13', $product13pin->getId()));
            $product13pin->setName(sprintf('%s - 13 pin', $product13pin->getName()));
            array_push($productsWithPins, $product13pin);
        }
        return $productsWithPins;
    }

    /**
     * Get ID
     *
     * @param Crawler $containerHtmlElement
     * @return string|null
     */
    private function getId(Crawler $containerHtmlElement): ?string
    {
        $id = $this->getTextCrawler($containerHtmlElement->filter('div.column-indexes h4.title span'));
        $id = Str::slug($id);
        if (!$id) {
            return null;
        }
        return $id;
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

        $url = 'https://agrip.pl/produkty,' . $page . '?p%5Bfilter_vehicle%5D=&p%5Bfilter_collection%5D=1&p%5Bfilter_dateFrom%5D=&p%5Bfilter_dateTo%5D=&p%5Bfilter_search%5D=&p%5Battr_75%5D=';
        $content = $this->websiteClient->getContentAnonymous($url);
        $html = str_replace(['<!--', '-->'], '', $content);
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        return $this->getCrawler($html);
    }


    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $ean = $this->eanCodes[$product->getId()] ?? null;
        if ($ean) {
            $product->addAttribute('EAN', $ean, 150);
        }
        $sku = $this->getId($crawlerProduct);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $index = 300;
        $crawlerProduct->filter('div.column-indexes')
            ->each(function (Crawler $attributeContainer) use (&$product, &$index) {
                $title = $this->getTextCrawler($attributeContainer->filter('h4.column-title'));
                if (in_array($title, ['Hak', 'Pojazd'])) {
                    $attributeContainer->filter('ul.product-indexes')->first()->filter('li')
                        ->each(function (Crawler $liElement) use (&$product, &$index) {
                            $spans = $liElement->filter('span');
                            if ($spans->count() === 2) {
                                $name = $this->getTextCrawler($spans->eq(0));
                                $value = $this->getTextCrawler($spans->eq(1));
                                if (Str::contains($name, ':')) {
                                    $name = trim(str_replace([':', '&nbsp;'], '', $name));
                                    $name = str_replace('(kg)', '[kg]', $name);
                                    if (Str::contains($name, ' [')) {
                                        $unit = explode(' [', $name)[1] ?? '';
                                        $unit = explode(']', $unit)[0];
                                        if ($unit) {
                                            $name = explode(' [', $name)[0];
                                            $value = sprintf('%s %s', $value, $unit);
                                        }
                                    }
                                    if ($name && $value) {
                                        if ($name === 'Rok produkcji' && Str::contains($value, 'obecnie')) {
                                            $value = str_replace('obecnie', now()->format('Y-m'), $value);
                                        }
                                        $index += 50;
                                        if (!$product->getAttributeValue($name)){
                                            $product->addAttribute($name, $value, $index);
                                        }
                                    }
                                }
                            }
                        });
                }
            });
    }

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addNameProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $name = 'Hak holowniczy';
        $attributes = $product->getAttributes();
        $version = '';
        $yearProduction = '';
        foreach ($attributes as $attribute) {
            $nameAttribute = $attribute->getName();
            if (in_array($nameAttribute, ['Marka', 'Model', 'Wersja', 'Rok produkcji'])) {
                if ($nameAttribute === 'Wersja') {
                    $version = $attribute->getValue();
                } else if ($nameAttribute === 'Rok produkcji') {
                    $yearProduction = $attribute->getValue();
                } else {
                    $name .= sprintf(' %s', $attribute->getValue());
                }
            }
        }
        preg_match_all('!\d+!', $yearProduction, $years);
        $yearNumbers = $years[0] ?? [];
        $newYears = '';
        foreach ($yearNumbers as $yearNumber) {
            if (strlen($yearNumber) === 4) {
                if ($newYears) {
                    $newYears .= ' - ';
                }
                $newYears .= $yearNumber;
            }
        }
        if ($newYears) {
            $name .= sprintf(' %s', $newYears);
        }
        if ($version) {
            $name .= sprintf(' %s', $version);
        }
        $name = trim(str_replace(', ', ' ', $name));
        $product->setName($name);
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $urlModel = sprintf('https://storage.waw.cloud.ovh.net/v1/AUTH_88b6bc0de1634d20b552cce1e493ff2c/NetLinker/resources/deliverer-agrip/images/modele/%s.jpg', mb_strtoupper($product->getId()));
        $urlIsometry = sprintf('https://storage.waw.cloud.ovh.net/v1/AUTH_88b6bc0de1634d20b552cce1e493ff2c/NetLinker/resources/deliverer-agrip/images/izometrie/%s.jpg', mb_strtoupper($product->getId()));
        $urls[sprintf('model_%s.jpg', mb_strtoupper($product->getId()))] = $urlModel;
        $urls[sprintf('isomery_%s.jpg', mb_strtoupper($product->getId()))] = $urlIsometry;
        foreach ($urls as $filenameUnique => $url) {
            if ($this->activeUrlImage($url)) {
                $main = sizeof($product->getImages()) === 0;
                $id = $filenameUnique;
                $product->addImage($main, $id, $url, $filenameUnique);
            }
        }
    }


    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addDescriptionProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $description = '<div class="description">';
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                if (!in_array(mb_strtolower($attribute->getName()), ['sku', 'ean'])) {
                    $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
                }
            }
            $description .= '</ul></div>';
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Initialize B2B data products
     *
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function initB2bDataProducts(): void
    {
        $path = __DIR__ . '/../../../../../resources/data/b2b_products.data';
        $this->b2bDataProducts = $this->resourceRemember($path, 172800, function () {
            $products = iterator_to_array($this->b2bListProducts->get());
            $productsToRemember = [];
            /** @var ProductSource $product */
            foreach ($products as $product) {
                $productsToRemember[$product->getId()] = $product;
            }
            return $productsToRemember;
        });
    }

    /**
     * Get B2b data product
     *
     * @param string $id
     * @return ProductSource|null
     */
    private function getB2bDataProduct(string $id): ?ProductSource
    {
        return $this->b2bDataProducts[$id] ?? null;
    }

    /**
     * Active URL image
     *
     * @param string $urlModel
     * @return bool
     */
    private function activeUrlImage(string $urlModel): bool
    {
        $client = new Client(['verify' => false]);
        try {
            $client->head($urlModel);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     */
    private function addCategoryProduct(ProductSource $product): void
    {
        $category = new CategorySource('haki_hol', 'Haki holownicze', 'https://agrip.pl');
        $brand = '';
        $model = '';
        foreach ($product->getAttributes() as $attribute){
            if ($attribute->getName() === 'Marka'){
                $brand = $attribute->getValue();
            } else if ($attribute->getName()==='Model'){
                $model = $attribute->getValue();
            }
        }
        if ($brand && $model){
            $idBrand = Str::slug(sprintf('haki_%s', $brand));
            $categoryBrand = new CategorySource($idBrand, $brand, 'https://agrip.pl');
            $idModel = Str::slug(sprintf('haki_%s_%s', $brand,  $model));
            $categoryModel = new CategorySource($idModel, $model, 'https://agrip.pl');
            $category->addChild($categoryBrand);
            $categoryBrand->addChild($categoryModel);
        } else {
            $category->addChild(new CategorySource('pozostale', 'Pozostałe', 'https://agrip.pl'));
        }
        $product->addCategory($category);
    }

    /**
     * Initialize EAN codes
     *
     * @throws GuzzleException
     */
    private function initEanCodes(): void
    {
        $reader = new CsvFileReader(__DIR__ . '/../../../../../resources/data/kody_ean.csv');
        $rows = $reader->getRows();
        $this->eanCodes = [];
        foreach ($rows as $row){
            $ean = trim($row['EAN domyślny']);
            if ($ean){
                $this->eanCodes[Str::slug($row['Indeks'])] = $ean;
            }
        }
    }

}
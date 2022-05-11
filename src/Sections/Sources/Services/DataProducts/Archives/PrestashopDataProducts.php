<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Enums\Countries;
use NetLinker\DelivererAgrip\Sections\Sources\Enums\Currencies;
use NetLinker\DelivererAgrip\Sections\Sources\Enums\Languages;
use NetLinker\DelivererAgrip\Sections\Sources\Services\CurrencyExchange;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XlsFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\PrestashopWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use Symfony\Component\DomCrawler\Crawler;

class PrestashopDataProducts implements DataProducts
{
    use CrawlerHtml, ExtensionExtractor, CleanerDescriptionHtml;

    /** @var PrestashopWebapiClient $webapiClient */
    protected $webapiClient;

    /** @var array $categories */
    protected $categories;

    /** @var array $dataPrices */
    protected $dataPrices;

    /** @var CurrencyExchange $currencyExchange */
    protected $currencyExchange;

    /**
     * AspDataProducts constructor
     *
     * @param string $urlApi
     * @param string $apiKey
     * @param bool $debug
     */
    public function __construct(string $urlApi, string $apiKey, bool $debug = false)
    {
        $this->webapiClient = app(PrestashopWebapiClient::class, [
            'urlApi' => $urlApi,
            'apiKey' => $apiKey,
            'debug' =>$debug,
        ]);
        $this->currencyExchange = app(CurrencyExchange::class);
    }

    /**
     * Get
     *
     * @param ProductSource|null $product
     * @return Generator|ProductSource[]
     * @throws DelivererAgripException
     */
    public function get(?ProductSource $product = null): Generator
    {
        $this->initCategories();
        $this->initDataPrices();
        $productIds = $this->getIdsProducts();
        foreach ($productIds as $productId) {
            $product = $this->getProduct($productId);
            if ($product){
                yield $product;
            }
        }
    }

    /**
     * Get Id's products
     *
     * @return array
     */
    private function getIdsProducts(): array
    {
        $products = $this->webapiClient->Products()->findAll([
            'display' => '[id,active,type,reference]'
        ]);
        $products = json_decode(json_encode($products), TRUE)['products']['product'] ?? [];
        $ids = [];
        foreach ($products as $product) {
            $id = $product['id'];
            if ($product['active'] === '1' && $product['type'] === 'simple' && !in_array($id, $ids)) {
                $reference = $product['reference'] ?: null;
                if ($reference){
                    array_push($ids, (string) $id);
                }
            }
        }
        return $ids;
    }

    /**
     * Init categories
     */
    private function initCategories(): void
    {
        $this->categories = [];
        $categories = $this->webapiClient->Categories()->findAll([
            'display' => '[id,id_parent,name]'
        ]);
        $categories = json_decode(json_encode($categories), TRUE)['categories']['category'] ?? [];
        foreach ($categories as $category) {
            $name = $category['name']['language'];
            $this->categories[$category['id']] = [
                'id' => $category['id'],
                'id_parent' => $category['id_parent'],
                'name' => is_array($name) ? $name[0] : $name,
            ];
        }
    }

    /**
     * Get product
     *
     * @param string $productId
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function getProduct(string $productId): ?ProductSource
    {
        DelivererLogger::log(sprintf('Get data product %s.', $productId));
        $productPrestashop = $this->webapiClient->Products()->find($productId);
        $productPrestashop = json_decode(json_encode($productPrestashop), TRUE)['product'] ?? [];
        $id = trim($productPrestashop['id']) ?: null;
        if (!$id){
            return null;
        }
        $url = sprintf('http://agrip.de/%s-.html', $id);
        $product = new ProductSource($id, $url, Languages::GERMANY, Currencies::EURO, Countries::GERMANY);
        $this->addPriceProduct($product, $productPrestashop);
        $this->addCategoriesProduct($product, $productPrestashop);
        $this->addNameProduct($product, $productPrestashop);
        $this->addImagesProduct($product, $productPrestashop);
        $this->addAttributesProduct($product, $productPrestashop);
        $this->addDescriptionProduct($product, $productPrestashop);
        $product->setTax(19);
        $product->setAvailability(1);
        $product->setStock(15);
        $this->removeLongAttributes($product);
        $product->check();
        return $product;
    }

    /**
     * Add attribute product
     *
     * @param ProductSource $product
     * @param array $productPrestashop
     */
    private function addAttributesProduct(ProductSource $product, array $productPrestashop): void
    {
        $sku = $productPrestashop['reference'];
        $ean = $productPrestashop['ean13'];
        if ($ean){
            $ean = is_array($ean) ? $ean[0] : $ean;
        }
        if ($sku) {
            $product->addAttribute('SKU', $sku, 20);
        }
        if ($ean) {
            $product->addAttribute('EAN', $ean, 30);
        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param array $productPrestashop
     */
    private function addDescriptionProduct(ProductSource $product, array $productPrestashop): void
    {
        $description = '<div class="description">';
        $descriptionWebapiProduct = $this->getDescriptionWebApiProduct($product, $productPrestashop);
        if ($descriptionWebapiProduct) {
            $product->setProperty('description_raw', $descriptionWebapiProduct);
            $description .= sprintf('<div class="content-section-description" id="description_extra3">%s</div>', $descriptionWebapiProduct);
        }
        $attributes = []; // $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra2"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get description web API product
     *
     * @param ProductSource $product
     * @param array $productPrestashop
     * @return string
     */
    private function getDescriptionWebApiProduct(ProductSource $product, array $productPrestashop): string
    {
        $descriptionWebApi = $productPrestashop['description']['language'];
        return is_array($descriptionWebApi) ? $descriptionWebApi[0] : $descriptionWebApi;
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $productPrestashop
     * @return void
     */
    private function addImagesProduct(ProductSource $product, array $productPrestashop): void
    {
        $idsImages = $this->getIdsImages($productPrestashop);
        foreach ($idsImages as $id) {
            $url = 'http://agrip.de/' . $id . '/-.jpg';
            $filenameUnique = sprintf('%s.jpg', $id);
            $main = sizeof($product->getImages()) === 0;
            $product->addImage($main, $id, $url, $filenameUnique);
        }
    }

    /**
     * Get ID's images
     *
     * @param array $productPrestashop
     * @return array
     */
    private function getIdsImages(array $productPrestashop): array
    {
        $images = $productPrestashop['associations']['images']['image'];
        if (isset($images['id'])) {
            return [$images['id']];
        }
        $ids = [];
        foreach ($images as $image) {
            $id = $image['id'];
            if (!in_array($id, $ids)) {
                array_push($ids, $id);
            }
        }
        return $ids;
    }

    /**
     * Remove long attributes
     *
     * @param ProductSource $product
     */
    private function removeLongAttributes(ProductSource $product): void
    {
        $attributes = $product->getAttributes();
        foreach ($attributes as $index => $attribute){
            if (mb_strlen($attribute->getName()) > 50){
                unset($attributes[$index]);
            }
        }
        $product->setAttributes($attributes);
    }

    /**
     * Add price product
     *
     * @param ProductSource $product
     * @param array $productPrestashop
     */
    private function addPriceProduct(ProductSource $product, array $productPrestashop): void
    {
        $index = Str::replaceLast('-WP', '', mb_strtoupper($productPrestashop['reference']));
        if ($index && isset($this->dataPrices[$index])){
            $pricePl = $this->dataPrices[$index]['price'];
            $price = $this->currencyExchange->getPrice($pricePl, 'pln', 'eur');
        } else {
            $price = (float) $productPrestashop['price'];
            if (!$price){
                $price = 1.0;
            }
        }
        $product->setPrice((float)$price);
    }

    /**
     * Init data prices
     */
    private function initDataPrices(): void
    {
        $keyCache = sprintf('%s_%s', get_class($this), 'data_prices');
        $this->dataPrices = Cache::remember($keyCache, 3600, function(){
            $dataExcel = Excel::toArray(new XlsFileReader, __DIR__ .'/../../../../../resources/data/cennik.xls')[0];
            $dataPrices = [];
            foreach ($dataExcel as $rowExcel){
               $product1= [
                    'index' =>$rowExcel[0],
                    'name_pl' =>$rowExcel[1],
                    'price' => (float) $rowExcel[3],
                ];
                $product2= [
                    'index' =>$rowExcel[5],
                    'name_pl' =>$rowExcel[6],
                    'price' => (float) $rowExcel[8],
                ];
                if ($product1['price']){
                    $dataPrices[$product1['index']] = $product1;
                }
                if ($product2['price']){
                    $dataPrices[$product2['index']] = $product2;
                }
            }
            return $dataPrices;
        });
    }

    /**
     * Add categories product
     *
     * @param ProductSource $product
     * @param array $productPrestashop
     * @throws DelivererAgripException
     */
    private function addCategoriesProduct(ProductSource $product, array $productPrestashop): void
    {
        $idCategory = array_reduce($productPrestashop['associations']['categories']['category'] ?? [], function($carry, $category){
            if ($category['id'] > $carry){
                $carry = $category['id'];
            }
            return $carry;
        }, $productPrestashop['id_category_default']);
        $categoryPrestashop = $this->categories[$idCategory];
        $category = null;
        $categoryLast = null;
        while($categoryPrestashop){
            if ($categoryPrestashop['id'] === '1'){
                break;
            } else if ($categoryPrestashop['id'] === '2' && $category){
                break;
            }
            $category = new CategorySource($categoryPrestashop['id'], $categoryPrestashop['name'], sprintf('https://agrip.de/%s-', $categoryPrestashop['id']));
            if ($categoryLast){
                $category->setChildren([$categoryLast]);
            }
            $categoryLast = $category;
            $categoryPrestashop = $this->categories[$categoryPrestashop['id_parent']] ?? null;
        }
        if (!$category){
            throw new DelivererAgripException(sprintf('Not found categories product %s.', $product->getId()));
        }
        $product->setCategories([$category]);
    }

    /**
     * Add name
     *
     * @param ProductSource $product
     * @param array $productPrestashop
     */
    private function addNameProduct(ProductSource $product, array $productPrestashop): void
    {
        $name = $productPrestashop['name']['language'];
        $name = is_array($name) ? $name[0] : $name;
        $index = Str::replaceLast('-WP', '', mb_strtoupper($productPrestashop['reference']));
        if ($index && isset($this->dataPrices[$index])){
            $namePl = $this->dataPrices[$index]['name_pl'];
            if ($namePl){
                $heightExplode = explode('H:', $name);
                if (sizeof($heightExplode) === 2){
                    $height = trim($heightExplode[1]);
                    if ($height) {
                        $namePl = sprintf('%s wys. %s', $namePl, $height);
                    }
                }
                $product->setProperty('name_pl', $namePl);
            }
        }

        $product->setName($name);
    }
}
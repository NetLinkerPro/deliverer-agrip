<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use DOMDocument;
use Exception;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\EanValidator;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FtpDownloader;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class XmlTwoclickListProducts implements ListProducts
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor, ExtensionExtractor, EanValidator;

    /** @var string $url */
    protected $url;

    /** @var string $login */
    protected $login;

    /** @var string $login */
    protected $password;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $url
     * @param string $login
     * @param string $password
     */
    public function __construct(string $url, string $login, string $password)
    {
        $this->url = $url;
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Get
     *
     * @param CategorySource|null $category
     * @return Generator
     * @throws DelivererAgripException
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function get(?CategorySource $category = null): Generator
    {
        $products = $this->getXmlProducts();
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get XML products
     *
     * @return Generator
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws DelivererAgripException
     * @throws Exception
     */
    private function getXmlProducts(): Generator
    {
        $url = sprintf('https://%s:%s@%s', $this->login, $this->password, str_replace(['https://', 'http://'], '', $this->url));
        $xmlReader = new XmlFileReader($url);
        $xmlReader->setTagNameProduct('product');
        $xmlReader->setDownloadBefore(app()->environment() === 'production');
        $xmlProducts = $xmlReader->read();
        $ids = [];
        foreach ($xmlProducts as $xmlProduct) {
            $id = $this->getIdProduct($xmlProduct);
           if (in_array($id, $ids)){
               continue;
           }
           array_push($ids, $id);
            if (!$id){
                DelivererLogger::log('Not found ID');
                continue;
            }
            $price = $this->getPriceProduct($xmlProduct);
            if (!$price) {
                DelivererLogger::log('Not found price');
                continue;
            }
            $stock = (int) $this->getStringXml($xmlProduct->stock);
            $url =  'https://agrip.pl/pl';
            $product = new ProductSource($id, $url);
            $product->setAvailability(1);
            $product->setStock($stock);
            $product->setPrice($price);
            $this->addTaxProduct($product, $xmlProduct);
            $this->addNameProduct($product, $xmlProduct);
            $hasCategory = $this->addCategoryProduct($product, $xmlProduct);
            if (!$hasCategory){
                continue;
            }
            $this->addImagesProduct($product, $xmlProduct);
            $product->setProperty('xml_product', $xmlProduct);
            yield $product;
        }
    }

    /**
     * Add tax product
     *
     * @param ProductSource $product
     * @param $xmlProduct
     */
    private function addTaxProduct(ProductSource $product, $xmlProduct): void
    {
        $tax = (int) $this->getStringXml($xmlProduct->vat);
        $product->setTax($tax);
    }

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param $xmlProduct
     * @throws DelivererAgripException
     */
    private function addNameProduct(ProductSource $product, $xmlProduct): void
    {
        $name = $this->getStringXml($xmlProduct->name);
        $product->setName($name);
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param $xmlProduct
     * @return bool
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, $xmlProduct): bool
    {
        $categories = [];
        $breadcrumbs = $this->getBreadcrumbProduct($xmlProduct);
        if (!$breadcrumbs){
            return false;
        }
        $explodeBreadcrumbs = explode('/', $breadcrumbs);
        $id = '';
        foreach ($explodeBreadcrumbs as $index => $breadcrumb) {
            $name = $breadcrumb;
            $breadcrumb = trim($breadcrumb);
            $breadcrumb = str_replace('-', '', Str::slug($breadcrumb));
            if ($breadcrumb){
                $id .= $id ? '_' : '';
                $id .= $breadcrumb;
                if (mb_strlen($id) > 64) {
                    $id = substr($id, -64, 64);
                    DelivererLogger::log(sprintf('Shortened id category %s', $id));
                }
                $url = 'https://agrip.pl/';
                $category = new CategorySource($id, $name, $url);
                array_push($categories, $category);
            }
        }
        if (!$categories) {
            throw new DelivererAgripException('Not found category product');
        }
        $categories = array_reverse($categories);
        $categoryProduct = null;
        /** @var CategorySource $category */
        foreach ($categories as $category) {
            if (!$categoryProduct) {
                $categoryProduct = $category;
            } else {
                $category->addChild($categoryProduct);
                $categoryProduct = $category;
            }
        }
        $product->addCategory($categoryProduct);
        return true;
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     * @throws Exception
     */
    private function addImagesProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $xmlImages = $xmlProduct->xpath('./foto/item');
        $urls = [];
        foreach ($xmlImages as $xmlImage) {
            $url = $this->getStringXml($xmlImage);
            if ($url && Str::contains($url, '.jpg') && !in_array($url, $urls)){
                array_push($urls, $url);
                $urlExplode = explode('/', $url);
                $filenameUnique = $urlExplode[sizeof($urlExplode)-1];
                $id = $filenameUnique;
                $main = sizeof($product->getImages()) === 0;
                $product->addImage($main, $id, $url, $filenameUnique);
            }
        }
    }

    /**
     * Get ID product
     *
     * @param SimpleXMLElement $xmlProduct
     * @return string
     */
    private function getIdProduct(SimpleXMLElement $xmlProduct): string
    {
        $id = Str::slug($this->getStringXml($xmlProduct->symbol));
        DelivererLogger::log(sprintf('Product ID: %s.', $id));
        return $id;
    }

    /**
     * Get price product
     *
     * @param $xmlProduct
     * @return float
     */
    private function getPriceProduct($xmlProduct): float
    {
        $price = $this->getStringXml($xmlProduct->price_promotion_b2b);
        if (!$price){
            $price = $this->getStringXml($xmlProduct->price_net_b2b);
        }
        $price = str_replace(' zł', '', $price);
        return $this->extractFloat($price);
    }

    /**
     * Get breadcrumb product
     *
     * @param $xmlProduct
     * @return string
     */
    private function getBreadcrumbProduct($xmlProduct): string
    {
        $breadcrumb = '';
        $categories = $xmlProduct->xpath('./categories/category');
        foreach ($categories as $category) {
            $path = $this->getStringXml($category->path);
            if (strlen($path) > strlen($breadcrumb)){
                $breadcrumb = $path;
            }
        }
        return $breadcrumb;
    }
}
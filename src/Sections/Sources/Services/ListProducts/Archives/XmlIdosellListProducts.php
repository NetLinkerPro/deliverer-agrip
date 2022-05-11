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
use NetLinker\DelivererAgrip\Sections\Sources\Traits\HtmlDecimalUnicodeDecoder;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class XmlIdosellListProducts implements ListProducts
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor, ExtensionExtractor, HtmlDecimalUnicodeDecoder, EanValidator;

    /** @var string $urlFull */
    protected $urlFull;

    /** @var string $urlLight */
    protected $urlLight;

    /** @var array $dataLight */
    private $dataLight;

    /** @var bool $liveMode */
    private $liveMode;
    
    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $urlFull
     * @param string $urlLight
     */
    public function __construct(string $urlFull, string $urlLight, bool $liveMode = false)
    {
        $this->urlFull = $urlFull;
        $this->urlLight = $urlLight;
        $this->liveMode = $liveMode;
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
        $this->initDataLight();
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
        $xmlReader = new XmlFileReader($this->urlFull);
        $xmlReader->setTagNameProduct('product');
        $xmlReader->setDownloadBefore(app()->environment() === 'production');
        $xmlProducts = $xmlReader->read();
        foreach ($xmlProducts as $xmlProduct) {
            $id = $this->getStringXml($xmlProduct['id']);
            if (!isset($this->dataLight[$id])){
                continue;
            }
            $price = $this->dataLight[$id]['price'];
            if (!$price) {
                continue;
            }
            $stock = $this->dataLight[$id]['stock'];
            $url =  $this->getStringXml($xmlProduct->card['url']);
            $product = new ProductSource($id, $url);
            $product->setAvailability(1);
            $product->setStock($stock);
            $product->setPrice($price);
            $this->addTaxProduct($product, $xmlProduct);
            if ($this->liveMode){
                yield $product;
            } else {
                $this->addNameProduct($product, $xmlProduct);
                $this->addCategoryProduct($product, $xmlProduct);
                $this->addImagesProduct($product, $xmlProduct);
                $this->addAttributesProduct($product, $xmlProduct);
                $this->addDescriptionProduct($product, $xmlProduct);
                $product->removeLongAttributes();
                $product->check();
                yield $product;
            }
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
        $tax = $this->dataLight[$product->getId()]['tax'];
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
        $xmlNames = $xmlProduct->xpath('./description/name');
        foreach ($xmlNames as $xmlName) {
            $xml = $xmlName->asXML();
            if (Str::startsWith($xml, '<name xml:lang="pol"')){
                $name = $this->getStringXml($xmlName);
                $product->setName($name);
            }
        }
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param $xmlProduct
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, $xmlProduct): void
    {
        $productCrawler = $this->getProductCrawler($product);
        $categories = [];
        $productCrawler->filter('#breadcrumbs li.category a')
            ->each(function(Crawler $a, $index) use (&$categories){
                $name = $this->getTextCrawler($a);
                $url = sprintf('https://www.agrip.pl%s', $this->getAttributeCrawler($a, 'href'));
                if ($index){
                    $urlExplode = explode('-', $url);
                    $id = $urlExplode[sizeof($urlExplode) - 1];
                    $id = explode('.', $id)[0];
                    array_push($categories, new CategorySource($id, $name, $url));
                }
            });
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
        $xmlImages = $xmlProduct->xpath('./images/large/image');
        $urls = [];
        foreach ($xmlImages as $xmlImage) {
            $url = $this->getStringXml($xmlImage['url']);
            $id = $this->getStringXml($xmlImage['hash']);
            $extension = $this->extractExtension($url, 'jpg');
            if ($url && !in_array($url, $urls)){
                array_push($urls, $url);
                $filenameUnique = sprintf('%s.%s', $id, $extension);
                $id = $filenameUnique;
                $main = sizeof($product->getImages()) === 0;
                $product->addImage($main, $id, $url, $filenameUnique);
            }
        }
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     */
    private function addAttributesProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $manufacturer = $this->getStringXml($xmlProduct->producer['name']);
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 10);
        }
        $sku = $this->getStringXml($xmlProduct->sizes->size['code_producer']);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 20);
        }
        $ean = $sku;
        if ($ean && $this->isValidEan($ean)) {
            $product->addAttribute('EAN', $ean, 30);
        }
        $unit =  $this->getStringXml($xmlProduct->unit['name']);
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 40);
        }
//        $weight = (float) $this->getStringXml($xmlProduct->weight);
//        if ($weight) {
//            $weight = str_replace('.', ',', $weight);
//            $weight .= ' kg';
//            $product->addAttribute('Waga', $weight, 60);
//        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     * @throws DelivererAgripException
     */
    private function addDescriptionProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $description = '<div class="description">';
        $descriptionProduct = '';
        $xmlDescriptions = $xmlProduct->xpath('./description/long_desc');
        foreach ($xmlDescriptions as $xmlDescription) {
            $xml = $xmlDescription->asXML();
            if (Str::startsWith($xml, '<long_desc xml:lang="pol"')){
                $descriptionProduct = $this->getStringXml($xmlDescription);
            }
        }
        if ($descriptionProduct) {
            $descriptionProduct = $this->correctDescriptionProduct($descriptionProduct);
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionProduct);
        }
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Correct description product
     *
     * @param string $html
     * @return string
     * @throws DelivererAgripException
     */
    private function correctDescriptionProduct(string $html): string
    {
        $html = $this->decodeToUtf8($html);
        $crawlerDescription = $this->getCrawler($html);
        if (!$crawlerDescription->count()){
            return '';
        }
//        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
//            foreach ($crawler as $node) {
//                $node->parentNode->removeChild($node);
//            }
//        });
        $crawlerDescription->filter('img')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $crawlerDescription->filter('table')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });

        $descriptionWebsite = trim($crawlerDescription->filter('body')->html());
        $descriptionWebsite = preg_replace('/<a[^>]+\>/i', '', $descriptionWebsite);
        $descriptionWebsite =preg_replace('/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', '', $descriptionWebsite);

//        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
//        if (Str::startsWith($descriptionWebsite, '<br>')) {
//            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
//        }
        if ($descriptionWebsite) {
            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * Initialize data light
     */
    private function initDataLight(): void
    {
        $keyCache = sprintf('%s_data_light_2', get_class($this));
        $this->dataLight = Cache::remember($keyCache, 3600, function(){
            $readerXml = new XmlFileReader($this->urlLight);
            $readerXml->setTagNameProduct('product');
            $readerXml->setDownloadBefore(app()->environment() === 'production');
            $dataLight = [];
            $products = $readerXml->read();
            foreach ($products as $productXml){
                $id = $this->getStringXml($productXml['id']);
                $stock = (int) $this->getStringXml($productXml->sizes->size->stock['quantity']);
                $price = (float) $this->getStringXml($productXml->price['net']);
                $tax = (int) $this->getStringXml($productXml->price['vat']);
                $dataLight[$id] = [
                    'id' => $id,
                    'stock' =>$stock,
                    'price' =>$price,
                    'tax' =>$tax,
                ];
            }
            return $dataLight;
        });
    }

    /**
     * Get product crawler
     *
     * @param ProductSource $product
     * @return Crawler
     */
    private function getProductCrawler(ProductSource $product): Crawler
    {
        DelivererLogger::log(sprintf('Get product contents %s', $product->getId()));
        $client = new Client(['verify'=>false]);
        $response = $client->get($product->getUrl());
        $contents = $response->getBody()->getContents();
        return $this->getCrawler($contents);
    }
}
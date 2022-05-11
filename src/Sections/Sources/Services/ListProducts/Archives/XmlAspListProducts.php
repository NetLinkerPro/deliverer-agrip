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
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FtpDownloader;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\HtmlDecimalUnicodeDecoder;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class XmlAspListProducts implements ListProducts
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor, ExtensionExtractor, HtmlDecimalUnicodeDecoder;

    /** @var string $urlFull */
    protected $urlFull;

    /** @var string $urlLight */
    protected $urlLight;

    /** @var array $dataLight */
    private $dataLight;
    
    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $urlFull
     * @param string $urlLight
     */
    public function __construct(string $urlFull, string $urlLight)
    {
        $this->urlFull = $urlFull;
        $this->urlLight = $urlLight;
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
            $id = $this->getStringXml($xmlProduct->id);
            $price = (float) str_replace(',', '.', $this->getStringXml($xmlProduct->priceAfterDiscountNet));
            if (!$price) {
                continue;
            }
            if (!isset($this->dataLight[$id])){
                continue;
            }
            $stock = $this->dataLight[$id]['stock'];
            $url =  $this->getStringXml($xmlProduct->url);
            $product = new ProductSource($id, $url);
            $product->setAvailability(1);
            $product->setStock($stock);
            $product->setPrice($price);
            $this->addTaxProduct($product, $xmlProduct);
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

    /**
     * Add tax product
     *
     * @param ProductSource $product
     * @param $xmlProduct
     */
    private function addTaxProduct(ProductSource $product, $xmlProduct): void
    {
        $tax = (int)$this->getStringXml($xmlProduct->vat);
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
        if (!$name) {
            throw new DelivererAgripException('Not found name.');
        }
        $product->setName($this->decodeToUtf8($name));
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
        $categories = [];
        $breadcrumbs = $this->decodeToUtf8($this->getStringXml($xmlProduct->categories->category[0]));
        $explodeBreadcrumbs = explode('\\', $breadcrumbs);
        $id = '';
        foreach ($explodeBreadcrumbs as $index => $breadcrumb) {
            $name = $breadcrumb;
            $breadcrumb = trim($breadcrumb);
            $breadcrumb = str_replace('-', '', Str::slug($breadcrumb));
            $id .= $id ? '_' : '';
            $id .= $breadcrumb;
            if (mb_strlen($id) > 64) {
                $id = substr($id, -64, 64);
                DelivererLogger::log(sprintf('Shortened id category %s', $id));
            }
            $url = 'https://agrip.net/';
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
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
        $xmlImages = $xmlProduct->xpath('./photos/*');
        $urls = [];
        foreach ($xmlImages as $xmlImage) {
            $url = (string) $xmlImage;
            $id = $this->getStringXml($xmlImage['id']);
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
        $manufacturer =$this->decodeToUtf8($this->getStringXml($xmlProduct->brand));
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 10);
        }
        $sku = $this->getStringXml($xmlProduct->sku);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 20);
        }
        $ean =  $this->getStringXml($xmlProduct->ean);
        if ($ean) {
            $product->addAttribute('EAN', $ean, 30);
        }
        $unit =  $this->getStringXml($xmlProduct->unit);
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 40);
        }
        $weight = (float) $this->getStringXml($xmlProduct->weight);
        if ($weight) {
            $weight = str_replace('.', ',', $weight);
            $weight .= ' kg';
            $product->addAttribute('Waga', $weight, 60);
        }
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
        $descriptionProduct = $this->getStringXml($xmlProduct->desc);
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
        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
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
        $keyCache = sprintf('%s_data_light', get_class($this));
        $this->dataLight = Cache::remember($keyCache, 3600, function(){
            $readerXml = new XmlFileReader($this->urlLight);
            $readerXml->setTagNameProduct('product');
            $readerXml->setDownloadBefore(app()->environment() === 'production');
            $dataLight = [];
            $products = $readerXml->read();
            foreach ($products as $productXml){
                $id = $this->getStringXml($productXml->id);
                $stock = (int) $this->getStringXml($productXml->qty);
                $dataLight[$id] = [
                    'id' => $id,
                    'stock' =>$stock,
                ];
            }
            return $dataLight;
        });
    }
}
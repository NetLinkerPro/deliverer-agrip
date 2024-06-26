<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Exception;
use Generator;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FtpDownloader;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;

class XmlSkyshopListProducts implements ListProducts
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor, ExtensionExtractor;

    /** @var string $url */
    protected $url;

    /**
     * SupremisB2bListCategories constructor
     */
    public function __construct(string $url)
    {
        $this->url = $url;
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
        $xmlReader = new XmlFileReader($this->url);
        $xmlReader->setTagNameProduct('item');
        if (Str::contains($this->url, 'http')){
            $xmlReader->setDownloadBefore(true);
        }
        $xmlProducts = $xmlReader->read();
        foreach ($xmlProducts as $xmlProduct) {
            $id = $this->getStringXml($xmlProduct->prod_id);
            $price = $this->getPrice($xmlProduct);
            $stock = $this->getStock($xmlProduct);
            if (!$price) {
                continue;
            }
            $url = $this->getStringXml($xmlProduct->prod_link);
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
        $tax = (int) $this->getStringXml($xmlProduct->taxpercent);
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
        $name = $this->getStringXml($xmlProduct->prod_name);
        if (!$name) {
            throw new DelivererAgripException('Not found name.');
        }
        $product->setName($name);
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
        $breadcrumbs = $this->getStringXml($xmlProduct->cat_path);
        $breadcrumbs = str_replace('_Import SKYSHOP', '', $breadcrumbs);
        $explodeBreadcrumbs = explode('/', $breadcrumbs);
        $id = '';
        foreach ($explodeBreadcrumbs as $index => $breadcrumb) {
            if (!$breadcrumb){
                continue;
            }
            $name = $breadcrumb;
            $breadcrumb = trim($breadcrumb);
            $breadcrumb = str_replace('-', '', Str::slug($breadcrumb));
            $id .= $id ? '_' : '';
            $id .= $breadcrumb;
            if (mb_strlen($id) > 64) {
                $id = substr($id, -64, 64);
                DelivererLogger::log(sprintf('Shortened id category %s', $id));
            }
            $url = 'https://b2b.agrip.pl/';
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
        }
        if (!$categories) {
            $category = new CategorySource('pozostale', 'Pozostałe', 'https://agrip.pl');
            array_push($categories, $category);
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
        $xmlImages = $xmlProduct->xpath('./prod_img/*');
        $urls = [];
        foreach ($xmlImages as $index=> $xmlImage) {
            $url = (string) $xmlImage;
            if ($url && !in_array($url, $urls)){
                array_push($urls, $url);
                $id = sprintf('%s_%s', $product->getId(), $index + 1);
                $extension = $this->extractExtension($url, 'jpg');
                $filenameUnique = sprintf('%s.%s', $id, $extension);
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
        $manufacturer = $this->getStringXml($xmlProduct->prd_name);
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 10);
        }
        $sku = $this->getStringXml($xmlProduct->prod_symbol);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 20);
        }
        $ean =  $this->getStringXml($xmlProduct->prod_ean);
        if ($ean) {
            $product->addAttribute('EAN', $ean, 30);
        }
        $unit =  $this->getStringXml($xmlProduct->prod_unit);
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 50);
        }
//        $parameters = $xmlProduct->xpath('./parms/*');
//        foreach ($parameters as $index => $parameter){
//            $name = $this->getStringXml($parameter['name']);
//            $value = $this->getStringXml($parameter);
//            if ($name && $value){
//                $name = str_replace('-', ' ', $name);
//                $name = Str::ucfirst($name);
//                if (!$product->getAttributeValue($name) && !Str::contains($name, 'kurierska') && !Str::contains($name, 'dl-x')){
//                    $order = 200 + ($index * 20);
//                    $product->addAttribute($name, $value, $order);
//                }
//            }
//        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     */
    private function addDescriptionProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $description = '<div class="description">';
        $descriptionProduct = $this->getStringXml($xmlProduct->description);
        if ($descriptionProduct) {
            if (Str::contains($descriptionProduct, '&')){
                $descriptionProduct = html_entity_decode($descriptionProduct);
            }
            $descriptionProduct = str_replace('</li><br />', '</li>', $descriptionProduct);
            $descriptionProduct = str_replace('<br /> <br />', '<br />', $descriptionProduct);
//            $descriptionProduct = str_replace('<br /><br />', '<br />', $descriptionProduct);
            $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
            $descriptionProduct= preg_replace($regex, ' ', $descriptionProduct);
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
     * Get price
     *
     * @param $xmlProduct
     * @return float|null
     */
    private function getPrice($xmlProduct):?float
    {
       return (float) $this->getStringXml($xmlProduct->prod_price_net);
    }

    /**
     * Get stock
     *
     * @param $xmlProduct
     * @return int
     */
    private function getStock($xmlProduct): int
    {
       return (int) $this->getStringXml($xmlProduct->prod_amount);
    }
}
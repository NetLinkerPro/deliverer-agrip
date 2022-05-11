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
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\Csv2FileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FtpDownloader;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;

class Ftp2ListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor, LimitString;

    /** @var string $host */
    protected $host;

    /** @var string $loginFtp */
    protected $loginFtp;

    /** @var string $passwordFtp */
    protected $passwordFtp;

    /** @var string|null $fromAddProduct */
    protected $fromAddProduct;

    /**
     * Constructor
     *
     * @param string $host
     * @param string $login
     * @param string $password
     * @param string|null $fromAddProduct
     */
    public function __construct(string $host, string $login, string $password, ?string $fromAddProduct = null)
    {
        $this->host = $host;
        $this->loginFtp = $login;
        $this->passwordFtp = $password;
        $this->fromAddProduct = $fromAddProduct;
    }

    /**
     * Get
     *
     * @return Generator|ProductSource[]|array
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function get(): Generator
    {
        $products = $this->getFtpProducts();
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get FTP products
     *
     * @return Generator
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function getFtpProducts(): Generator
    {
        $uri = $this->downloadCsvFile();
        try {
            $csvFileReader = $this->getCsvFileReader($uri);
            $rows = $csvFileReader->getRows();
            foreach ($rows as $csvProduct) {
                $id = trim($csvProduct['kod wlasny']);
                $url = sprintf('https://agrip.pl/%s', $id);
                $product = new ProductSource($id, $url);
                DelivererLogger::log(sprintf('Product %s.', $id));
                $product->setAvailability(1);
                $product->setTax($this->getProductTax($csvProduct));
                $price = $this->getProductPrice($csvProduct);
                if (!$price) {
                    DelivererLogger::log(sprintf('Not found price for ID product %s.', $id));
                    continue;
                }
                $product->setPrice($price);
                $this->addNameProduct($product, $csvProduct);
                $this->addStockProduct($product, $csvProduct);
                $this->addCategoryProduct($product, $csvProduct);
                $this->addImagesProduct($product, $csvProduct);
                $this->addAttributesProduct($product, $csvProduct);
                $this->addDescriptionProduct($product, $csvProduct);
//                $this->removeSkuFromName($product);
                $product->removeLongAttributes();
                $product->check();
                yield $product;
            }
        } catch (Exception $e) {
            throw $e;
        } finally {
            unlink($uri);
        }
    }

    /**
     * Get CSV file reader
     *
     * @param string $uri
     * @return Csv2FileReader
     */
    private function getCsvFileReader(string $uri): Csv2FileReader
    {
        /** @var Csv2FileReader $csvReader */
        $csvReader = app(Csv2FileReader::class, ['uri' => $uri]);
        $csvReader->setFromEncoding('UTF-16LE');
        $csvReader->setDelimiter('|');
        return $csvReader;
    }

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param array $csvProduct
     * @throws DelivererAgripException
     */
    private function addNameProduct(ProductSource $product, array $csvProduct): void
    {
        $name = trim($csvProduct['nazwa']);
        $name= preg_replace('/\s+/', ' ',$name);
        if (!$name) {
            throw new DelivererAgripException('Not found name.');
        }
        $product->setName($name);
    }

    /**
     * Get product price
     *
     * @param array $csvProduct
     * @return float
     */
    private function getProductPrice(array $csvProduct): float
    {
        $priceText = $csvProduct['po rabacie'];
        if (!$priceText){
            $priceText = $csvProduct['cena netto'];
        }
        $price = str_replace([' ', 'PLN', '.'], '', $priceText);
        return $this->extractFloat(str_replace(',', '.', $price));
    }

    /**
     * Add stock product
     *
     * @param ProductSource $product
     * @param array $csvProduct
     */
    private function addStockProduct(ProductSource $product, array $csvProduct): void
    {
        $stock = (int) $csvProduct['stan magazynowy'];
        $product->setStock($stock);
    }


    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param array $csvProduct
     * @return void
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, array $csvProduct): void
    {
        $textCategory = $csvProduct['producent'];
        $explodeCategoryText = explode('>>>>', $textCategory);
        $id = '';
        /** @var CategorySource $lastCategory */
        $categoryRoot = null;
        $lastCategory = null;
        foreach ($explodeCategoryText as $name) {
            $name = str_replace('/', '|', $name);
            $name = trim($name);
            if ($name) {
                $id = sprintf('%s%s%s', $id, $id ? '-' : '', Str::slug($name));
                $id = $this->limitReverse($id, 64);
                $url = 'https://www.agrip.pl';
                $category = new CategorySource($id, $name, $url);
                if ($lastCategory) {
                    $lastCategory->addChild($category);
                }
                if (!$categoryRoot) {
                    $categoryRoot = $category;
                }
                $lastCategory = $category;
            }
        }
        if (!$categoryRoot) {
            throw new DelivererAgripException('Not found category.');
        }
        $product->setCategories([$categoryRoot]);
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $csvProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $csvProduct): void
    {

    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param array $csvProduct
     */
    private function addAttributesProduct(ProductSource $product, array $csvProduct): void
    {
        $manufacturer = trim($csvProduct['producent']);
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean = trim($csvProduct['Ean']);
        $ean = explode(',', $ean)[0] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
//        $sku = $this->getStringXml($xmlProduct->indeks_katalogowy);
//        if ($sku) {
//            $product->addAttribute('SKU', $sku, 200);
//        }
//        $unit = $this->getTextCrawler($cartCrawler);
//        if ($unit) {
//            $product->addAttribute('Jednostka', $unit, 500);
//        }
//        $this->addAttributesFromDataTechnicalTabProduct($product, $dataTabs);
//        $this->addAttributesFromDescriptionProduct($product, $crawlerProduct);
    }


    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param array $csvProduct
     */
    private function addDescriptionProduct(ProductSource $product, array $csvProduct): void
    {
        $description = '<div class="description">';
        $descriptionXml = '';
        if ($descriptionXml) {
            $crawler = $this->getCrawler($descriptionXml);
            $descriptionHtml = $crawler->filter('body')->html();
            $descriptionHtml = str_replace('<p><b>Zapraszamy do złożenia zamówienia!</b></p>', '', $descriptionHtml);
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionHtml);
        }
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                $attributeLowerText = mb_strtolower($attribute->getName());
                if (!in_array($attributeLowerText, ['sku'])) {
                    $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
                }
            }
            $description .= '</ul></div>';
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Remove SKU from name
     *
     * @param ProductSource $product
     */
    private function removeSkuFromName(ProductSource $product): void
    {
        $name = $product->getName();
        $sku = $product->getAttributeValue('SKU');
        if ($name !== $sku) {
            $name = str_replace($sku, '', $name);
            $name = preg_replace('/\s+/', ' ', $name);
            $name = trim($name);
            $product->setName($name);
        }
    }

    /**
     * Download XML file
     *
     * @return string
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws Exception
     */
    private function downloadCsvFile(): string
    {
        $path = storage_path('temp/deliverer_agrip/agrip.csv');
        $remotePath = '/oferta/oferta.csv';
        $this->downloadFileFtp($this->host, $this->loginFtp, $this->passwordFtp, $remotePath, $path);
        return $path;
    }

    /**
     * Get product tax
     *
     * @param $csvProduct
     * @return int
     */
    private function getProductTax($csvProduct): int
    {
        $tax = $csvProduct['vat'];
        return $this->extractInteger($tax);
    }
}
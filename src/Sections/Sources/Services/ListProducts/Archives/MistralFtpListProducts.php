<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Exception;
use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
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

class MistralFtpListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor, LimitString;

    /** @var string $loginFtp */
    protected $loginFtp;

    /** @var string $passwordFtp */
    protected $passwordFtp;

    /** @var string|null $fromAddProduct */
    protected $fromAddProduct;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $login
     * @param string $password
     * @param string|null $fromAddProduct
     */
    public function __construct(string $login, string $password, ?string $fromAddProduct = null)
    {
        $this->loginFtp = $login;
        $this->passwordFtp = $password;
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
     */
    private function getXmlProducts(): Generator
    {
        $uri = $this->downloadXmlFile();
        try {
            $xmlReader = new XmlFileReader($uri);
            $xmlReader->setTagNameProduct('produkt');
            $xmlProducts = $xmlReader->read();
            foreach ($xmlProducts as $xmlProduct) {
                $id = $this->getStringXml($xmlProduct->numer);
                $url = sprintf('http://www.agrip.pl/-/%s/produkt.aspx', $id);
                $product = new ProductSource($id, $url);
                DelivererLogger::log(sprintf('Product %s.', $id));
                $product->setAvailability(1);
                $product->setTax(23);
                $price = $this->getPriceProduct($product, $xmlProduct);
                if (!$price){
                    DelivererLogger::log(sprintf('Not found price for ID product %s.', $id));
                    continue;
                }
                $product->setPrice($price);
                $this->addNameProduct($product, $xmlProduct);
                $this->addStockProduct($product, $xmlProduct);
                $this->addCategoryProduct($product, $xmlProduct);
                $this->addImagesProduct($product, $xmlProduct);
                $this->addAttributesProduct($product, $xmlProduct);
                $this->addDescriptionProduct($product, $xmlProduct);
                $this->removeSkuFromName($product);
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
     * Add name product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     * @throws DelivererAgripException
     */
    private function addNameProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $name = $this->getStringXml($xmlProduct->nazwa);
        if (!$name) {
            throw new DelivererAgripException('Not found name.');
        }
        $product->setName($name);
    }

    /**
     * Get price product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     * @return float
     */
    private function getPriceProduct(ProductSource $product, SimpleXMLElement $xmlProduct): float
    {
        $priceText = $this->getStringXml($xmlProduct->cena_tylko_netto);
        $price = str_replace([' ', 'PLN', '.'], '', $priceText);
        return $this->extractFloat(str_replace(',', '.', $price));
    }

    /**
     * Add stock product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     * @throws DelivererAgripException
     */
    private function addStockProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $stockText = mb_strtolower($this->getStringXml($xmlProduct->stan));
        if ($stockText === 'brak') {
            $stock = 0;
        } else if ($stockText === 'mała ilość') {
            $stock = 3;
        } else if ($stockText === 'średnia ilość') {
            $stock = 7;
        } else if ($stockText === 'dużo') {
            $stock = 20;
        } else {
            throw new DelivererAgripException('Unknown stock.');
        }
        $product->setStock($stock);
    }


    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param SimpleXMLElement $xmlProduct
     * @return void
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $textCategory = htmlspecialchars_decode($this->getStringXml($xmlProduct->sciezka_kategorii));
        $explodeCategoryText = explode('>', $textCategory);
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
     * @param SimpleXMLElement $xmlProduct
     */
    private function addImagesProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $images = (array)($xmlProduct->lista_zdjec->zdjecie ?? null);
        if ($images) {
            foreach ($images as $url) {
                $main = sizeof($product->getImages()) === 0;
                $explodeUrl = explode('/', $url);
                $filenameUnique = $explodeUrl[sizeof($explodeUrl) - 1];
                $id = $filenameUnique;
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
//        $manufacturer = $this->getDataBasic('Producent', $crawlerProduct);
//        if ($manufacturer) {
//            $product->addAttribute('Producent', $manufacturer, 50);
//        }
        $ean = $this->getStringXml($xmlProduct->kod_kreskowy);
        $ean = explode(',', $ean)[0] ?? '';
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku = $this->getStringXml($xmlProduct->indeks_katalogowy);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
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
     * @param SimpleXMLElement $xmlProduct
     */
    private function addDescriptionProduct(ProductSource $product, SimpleXMLElement $xmlProduct): void
    {
        $description = '<div class="description">';
        $descriptionXml = $this->getStringXml($xmlProduct->opis);
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
                if (!in_array($attributeLowerText, ['ean', 'sku'])) {
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
    private function downloadXmlFile(): string
    {
        $path = storage_path('temp/deliverer_agrip/agrip.xml');
       try{
           $filename = sprintf('%s_mat.xml', now()->format('Y_m_d'));
           $this->downloadFileFtp('ftp.s35.hekko.pl', $this->loginFtp, $this->passwordFtp, $filename, $path);
       } catch (Exception $exception){
           $date = now();
           $date->subDay();
           $filename = sprintf('%s_mat.xml', $date->format('Y_m_d'));
           $this->downloadFileFtp('ftp.s35.hekko.pl', $this->loginFtp, $this->passwordFtp, $filename, $path);
       }
        return $path;
    }
}
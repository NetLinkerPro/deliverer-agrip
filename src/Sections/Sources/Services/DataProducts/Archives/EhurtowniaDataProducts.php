<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\CsvFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\InsolutionsListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;

class EhurtowniaDataProducts implements DataProducts
{
    use ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor;

    /** @var string $login */
    private $login;

    /** @var string $password */
    private $password;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Get
     *
     * @param ProductSource|null $product
     * @return Generator|ProductSource[]
     * @throws DelivererAgripException|GuzzleException
     */
    public function get(?ProductSource $product = null): Generator
    {
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
        $rowsCsv = $this->getRowsCsv();
        foreach ($rowsCsv as $rowCsv) {
            DelivererLogger::log(sprintf('%s %s %s', $rowCsv['Magazyn'], $rowCsv['kod wlasny'], $rowCsv['nazwa2']));
            $product = $this->getProduct($rowCsv);
            if ($product){
                yield $product;
            }
        }
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @param array $rowCsv
     */
    private function addCategoryProduct(ProductSource $product, array $rowCsv): void
    {
        $url = 'https://agrip.ehurtownia.pl';
        $id = $rowCsv['Magazyn'];
        $category1 = new CategorySource($id, $rowCsv['Magazyn'], $url);
        $id = sprintf('%s-%s', $id, Str::slug($rowCsv['grupa']));
        $category2 = new CategorySource($id, $rowCsv['grupa'], $url);
        $category1->addChild($category2);
        if ($subgroup1 = $rowCsv['podgrupa']){
            $id = $this->limitReverse(sprintf('%s-%s', $id, Str::slug($subgroup1)));
            $category3 = new CategorySource($id,$subgroup1, $url);
            $category2->addChild($category3);
            if ($subgroup2 = $rowCsv['podpodgrupa']){
                $id = $this->limitReverse(sprintf('%s-%s', $id, Str::slug($subgroup2)));
                $category4 = new CategorySource($id,$subgroup2, $url);
                $category3->addChild($category4);
            }
        }
        $product->setCategories([$category1]);
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param array $rowCsv
     */
    private function addAttributesProduct(ProductSource $product, array $rowCsv): void
    {
        $manufacturer = trim($rowCsv['marka']);
        if ($manufacturer) {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean = $rowCsv['Ean'];
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku = $rowCsv['kod wlasny'];
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $weight = $rowCsv['gramatura'];
        if ($weight) {
            $weight = sprintf('%s g', $weight);
            $product->addAttribute('Waga', $weight, 350);
        }
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $rowCsv
     */
    private function addImagesProduct(ProductSource $product, array $rowCsv): void
    {
        $urlImage = $rowCsv['foto'];
        if ($urlImage){
            $main = true;
            $explodeUrlImage = explode('/',$urlImage);
            $filenameUnique = $explodeUrlImage[sizeof($explodeUrlImage) - 1];
            $id = $filenameUnique;
            $product->addImage($main, $id, $urlImage, $filenameUnique);
        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     */
    private function addDescriptionProduct(ProductSource $product): void
    {
        $description = '<div class="description">';
        $attributes = $product->getAttributes();
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
     * Get rows CSV
     *
     * @return Generator
     * @throws GuzzleException
     */
    private function getRowsCsv(): Generator
    {
       $csvFileReader = $this->getCsvFileReader('11_P14731.csv');
       $rows =  $csvFileReader->getRows();
       foreach ($rows as $row){
           yield $row;
       }
        $csvFileReader = $this->getCsvFileReader('21_P14731.csv');
        $rows =  $csvFileReader->getRows();
        foreach ($rows as $row){
            yield $row;
        }
    }

    /**
     * Get csv file reader
     *
     * @param string $pathRemoteFtp
     * @return CsvFileReader
     */
    private function getCsvFileReader(string $pathRemoteFtp): CsvFileReader
    {
        $csvFileReader = new CsvFileReader('agrippolska.pl');
        $csvFileReader->setLoginFtp($this->login);
        $csvFileReader->setPasswordFtp($this->password);
        $csvFileReader->setRemotePathFtp($pathRemoteFtp);
        return $csvFileReader;
    }

    /**
     * Get product
     *
     * @param $rowCsv
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function getProduct($rowCsv): ?ProductSource
    {
        $id = sprintf('%s_%s', $rowCsv['Magazyn'], $rowCsv['kod wlasny']);
        $name = trim($rowCsv['nazwa2']) ?: trim($rowCsv['nazwa']);
        if (!$id || !$name) {
            return null;
        }
        $product = new ProductSource($id, 'https://agrip.ehurtownia.pl');
        $product->setName($name);
        $product->setStock((int) $rowCsv['Ilosc mag']);
        $product->setPrice($this->getPriceProduct($rowCsv));
        $product->setTax((int) $rowCsv['vat r']);
        $product->setAvailability(1);
        $this->addCategoryProduct($product, $rowCsv);
        $this->addImagesProduct($product, $rowCsv);
        $this->addAttributesProduct($product, $rowCsv);
        $this->addDescriptionProduct($product);
        $product->removeLongAttributes();
        $product->check();
        return $product;
    }

    /**
     * Get price product
     *
     * @param array $rowCsv
     * @return float
     */
    private function getPriceProduct(array $rowCsv): float
    {
        $textPrice = $rowCsv['cena netto'];
        $textPrice = str_replace([' ', ','], ['', '.'], $textPrice);
        return $this->extractFloat($textPrice);
    }
}
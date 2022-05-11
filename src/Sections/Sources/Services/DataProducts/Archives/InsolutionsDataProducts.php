<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\InsolutionsListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;

class InsolutionsDataProducts implements DataProducts
{
    use ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor;

    /** @var InsolutionsListProducts $listProducts */
    protected $listProducts;

    /** @var array $dataXml */
    protected $dataXml;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     * @param string|null $fromAddProduct
     */
    public function __construct(string $login, string $password, ?string $fromAddProduct = null)
    {
        $this->listProducts = app(InsolutionsListProducts::class, [
            'login' => $login,
            'password' => $password,
            'fromAddProduct' => $fromAddProduct,
        ]);
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
        $this->initDataXml();
        $products = $this->getProducts();
        foreach ($products as $product) {
            $product = $this->fillProduct($product);
            if ($product) {
                yield $product;
            }
        }
    }


    /**
     * Init data XML
     *
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function initDataXml(): void
    {
        $this->dataXml = $this->getDataXmlResourceRemember();
    }

    /**
     * Get data XML remember resource
     *
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDataXmlResourceRemember(): array
    {
        $pathResource = __DIR__ . '/../../../../../resources/data/data_xml.data';
        return $this->resourceRemember($pathResource, 3600, function () {
            return $this->getDataXml();
        });
    }

    /**
     * Get data site
     *
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDataXml(): array
    {
        $dataXml = [];
        $xmlFileReader = $this->getXmlFileReader();
        $xmlFileReader->read(function (SimpleXMLElement $productXml) use (&$dataXml) {
            $id = str_replace(['AGRIP', 'pl'], '', $productXml->children('g', true)->id);
            $category = $this->getCategoryProduct($productXml);
            $manufacturer = htmlspecialchars_decode($this->getStringXml($productXml->children('g', true)->brand));
            $ean = $this->getStringXml($productXml->children('g', true)->gtin);
            $sku = $this->getStringXml($productXml->children('g', true)->mpn);
            $weight = $this->getStringXml($productXml->children('g', true)->shipping_weight);
            $description = $this->getDescriptionProduct($productXml);
            $images = $this->getImagesProduct($productXml);
            $dataXml[$id] = [
                'id' => $id,
                'category' => $category,
                'manufacturer' => $manufacturer,
                'ean' => $ean,
                'sku' => $sku,
                'weight' => $weight,
                'description' => $description,
                'images' => $images,
            ];
        });
        return $dataXml;
    }

    /**
     * Fill product
     *
     * @param ProductSource $product
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function fillProduct(ProductSource $product): ?ProductSource
    {
        $id = $product->getId();
        $dataXml = $this->dataXml[$id] ?? null;
        if (!$dataXml) {
            return null;
        }
        $product->addCategory($dataXml['category']);
        $this->addImagesSettings($product);
        $this->addImagesProduct($product, $dataXml);
        $this->addAttributesProduct($product, $dataXml);
        $this->addDescriptionProduct($product, $dataXml);
        $product->removeLongAttributes();
        $product->check();
        return $product;
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
        $products = $this->listProducts->get();
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get XML file reader
     *
     * @return XmlFileReader
     */
    private function getXmlFileReader(): XmlFileReader
    {
        $url = 'https://www.agrip.pl/assets/default/integrations/client/AGRIP_extended.xml.gz';
        $xmlFileReader = new XmlFileReader($url);
        $xmlFileReader->setTagNameProduct('item');
        $xmlFileReader->setDownloadBefore(true);
        $xmlFileReader->setCompress('zlib');
        return $xmlFileReader;
    }

    /**
     * Get category product
     *
     * @param SimpleXMLElement $productXml
     * @return CategorySource
     */
    private function getCategoryProduct(SimpleXMLElement $productXml): CategorySource
    {
        $textCategory = htmlspecialchars_decode($this->getStringXml($productXml->children('g', true)->product_type));
        $explodeCategoryText = explode('>', $textCategory);
        $id = '';
        /** @var CategorySource $lastCategory */
        $categoryRoot = null;
        $lastCategory = null;
        foreach ($explodeCategoryText as $name) {
            $name = str_replace('/', '|', $name);
            $name = trim($name);
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
        return $categoryRoot;
    }

    /**
     * Get description product
     *
     * @param SimpleXMLElement $productXml
     * @return string
     */
    private function getDescriptionProduct(SimpleXMLElement $productXml): string
    {
        $html = htmlspecialchars_decode($this->getStringXml($productXml->html_description));
        $html = $this->cleanAttributesHtml($html);
        return $this->cleanEmptyTagsHtml($html);
    }

    /**
     * Get images product
     *
     * @param SimpleXMLElement $productXml
     * @return array
     */
    private function getImagesProduct(SimpleXMLElement $productXml): array
    {
        $images = [];
        $urlMain = $this->getStringXml($productXml->children('g', true)->image_link);
        if ($urlMain) {
            array_push($images, $urlMain);
        }
        $additionalImages = $productXml->xpath('//g:additional_image_link');
        foreach ($additionalImages as $additionalImage) {
            $urlAdditional = $this->getStringXml($additionalImage);
            if ($urlAdditional) {
                array_push($images, $urlAdditional);
            }
        }
        return $images;
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param array $dataXml
     */
    private function addAttributesProduct(ProductSource $product, array $dataXml): void
    {
        $manufacturer = $dataXml['manufacturer'];
        if ($manufacturer && $manufacturer !== 'Inne') {
            $product->addAttribute('Producent', $manufacturer, 50);
        }
        $ean = $dataXml['ean'];
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku = $dataXml['sku'];
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $weight = $dataXml['weight'];
        if ($weight) {
            $minimumOrder = $product->getProperty('minimum_order');
            if ($minimumOrder > 1) {
                $weightFloat = $this->extractFloat($weight);
                $weightFloat = $weightFloat * $minimumOrder;
                $weight = sprintf('%s kg /%s%s', str_replace('.', ',', $weightFloat), $minimumOrder, $product->getProperty('unit') ?: 'szt.');
            } else {
                $weight = str_replace('.', ',', $weight);
            }
            $product->addAttribute('Waga', $weight, 350);
        }
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $dataXml
     */
    private function addImagesProduct(ProductSource $product, array $dataXml): void
    {
        $images = $dataXml['images'];
        foreach ($images as $index => $image) {
            $main = sizeof($product->getImages()) === 0;
            $id = sprintf('%s-%s', $product->getId(), $index + 1);
            $url = $image;
            $filenameUnique = sprintf('%s.%s', $id, $this->extractExtension($image, 'png'));
            $product->addImage($main, $id, $url, $filenameUnique);
        }
    }


    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param array $dataXml
     */
    private function addDescriptionProduct(ProductSource $product, array $dataXml): void
    {
        $description = '<div class="description">';
        $descriptionXmlProduct = $dataXml['description'];
        $descriptionXmlProduct = $descriptionXmlProduct === 'opis' ? '' : $descriptionXmlProduct;
        if ($descriptionXmlProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra3">%s</div>', $descriptionXmlProduct);
        }
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
     * Add images settings
     *
     * @param ProductSource $product
     */
    private function addImagesSettings(ProductSource $product): void
    {
         $product->setProperty('image_setting_default_max_width', 800);
         $product->setProperty('image_setting_with_fill', null);
         $product->setProperty('image_setting_format', 'png');
    }
}
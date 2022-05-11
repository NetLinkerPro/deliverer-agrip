<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Generator;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use Symfony\Component\DomCrawler\Crawler;

class SoapDataProducts implements DataProducts
{
    use CrawlerHtml, ExtensionExtractor, CleanerDescriptionHtml;

    /** @var SoapWebapiClient $webapiClient */
    protected $webapiClient;

    /**
     * SoapDataProducts constructor
     *
     * @param string $token
     * @param string $login
     * @param string $password
     */
    public function __construct(string $token, string $login, string $password)
    {
        $this->webapiClient = app(SoapWebapiClient::class, [
            'token' => $token,
            'login' => $login,
            'password' => $password,
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
        if ($this->fillProduct($product)){
            yield $product;
        }
    }

    /**
     * Add attribute product
     *
     * @param ProductSource $product
     * @param Crawler $data
     */
    private function addAttributesProduct(ProductSource $product, Crawler $data): void
    {
        $sku = $this->getTextCrawler($data->filter('code'));
        $ean = $this->getTextCrawler($data->filter('eancode'));
        $codeManufacturer = $this->getTextCrawler($data->filter('codebyproducer'));
        if ($sku){
            $product->addAttribute('SKU', $sku, 20);
        }
        if ($ean){
            $product->addAttribute('EAN', $ean, 30);
        }
        if ($codeManufacturer){
            $product->addAttribute('Kod producenta', $codeManufacturer, 40);
        }
        $data->filter('attr rowproductattr')
            ->each(function (Crawler $attribute, $index) use (&$product) {
                $nameAttribute = $this->getTextCrawler($attribute->filter('name'));
                $valueAttribute = $this->getTextCrawler($attribute->filter('value'));
                $orderAttribute = ($index + 1) * 100;
                if ($nameAttribute && $valueAttribute && !in_array($nameAttribute, ['Gwarancja'])) {
                    if ($nameAttribute === 'Producent') {
                        $orderAttribute = 10;
                    }
                    $product->addAttribute($nameAttribute, $valueAttribute, $orderAttribute);
                }
            });
    }

    /**
     * Add category product
     *
     * @param ProductSource $product
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(ProductSource $product): void
    {
        $categories = [];
        $breadcrumbs = $product->getProperty('Kategoria');
        $explodeBreadcrumbs = explode(' > ', $breadcrumbs);
        $id = '';
        foreach ($explodeBreadcrumbs as $index => $breadcrumb){
            $name = $breadcrumb;
            $breadcrumb = trim($breadcrumb);
            $breadcrumb = str_replace('-','', Str::slug($breadcrumb));
            if (in_array($index, [0,1])){
                continue;
            }
            $id .= $id ? '_' : '';
            $id .= $breadcrumb;
            if (mb_strlen($id) > 64){
                $id = substr($id, -64, 64);
                DelivererLogger::log(sprintf('Shortened id category %s', $id));
            }
            $url = 'https://sklep.agrip.pl/';
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
     * Get unique filename image product
     *
     * @param string $url
     * @param int $countImage
     * @param ProductSource $product
     * @return string
     * @throws DelivererAgripException
     */
    private function getUniqueFilenameImageProduct(string $url, int $index, ProductSource $product): string
    {
        $explodeUrl = explode('/oryg/', $url);
        $uniqueFilename = $explodeUrl[1] ?? '';
        $uniqueFilename = str_replace(['/'], '', $uniqueFilename);
        if (!$uniqueFilename || Str::contains($uniqueFilename, ':')) {
            throw new DelivererAgripException('Invalid unique filename image product');
        }
        if (mb_strlen($uniqueFilename) > 50){
            $extension = $this->extractExtension($url, 'jpg');
            $uniqueFilename = sprintf('%s___%s.%s', $product->getId(), $index, $extension);
         }
        return $uniqueFilename;
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param Crawler $data
     */
    private function addDescriptionProduct(ProductSource $product, Crawler $data): void
    {
        $description = '<div class="description">';
        $descriptionWebapiProduct = $this->getDescriptionWebapiProduct($product, $data);
        if ($descriptionWebapiProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra3">%s</div>', $descriptionWebapiProduct);
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
     * Get description webapi product
     *
     * @param ProductSource $product
     * @param Crawler $data
     * @return string
     */
    private function getDescriptionWebapiProduct(ProductSource $product, Crawler $data): string
    {
        $descriptionWebapi = $data->filter('description')->html();
        if ($descriptionWebapi) {
            $descriptionWebapi = $this->cleanAttributesHtml($descriptionWebapi);
            $descriptionWebapi = $this->cleanEmptyTagsHtml($descriptionWebapi);
        }
        return $descriptionWebapi;
    }

    /**
     * @param ProductSource $product
     * @param Crawler $data
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, Crawler $data)
    {
        $data->filter('imgurl string')->each(function(Crawler $image, $index) use (&$product){
            $main = $index === 0;
            $url = $this->getTextCrawler($image);
            $filenameUnique = $this->getUniqueFilenameImageProduct($url, $index, $product);
            $id = $filenameUnique;
            $product->addImage($main, $id, $url, $filenameUnique);
        });
    }

    /**
     * Fill product
     *
     * @param ProductSource $product
     * @return bool
     * @throws DelivererAgripException
     */
    private function fillProduct(ProductSource $product): bool
    {
        DelivererLogger::log(sprintf('Get data product %s', $product->getId()));
        $body = $this->getBodyXmlProductInfo($product->getId());
        $contentXmlResponse = $this->webapiClient->request($body);
        $crawler = $this->getCrawler($contentXmlResponse);
        $name = $this->getTextCrawler($crawler->filter('name'));
        if (!$name){
            return false;
        }
        $product->setName($name);
        $this->addCategoryProduct($product);
        $this->addAttributesProduct($product, $crawler);
        $this->addDescriptionProduct($product, $crawler);
        $this->addImagesProduct($product, $crawler);
        $product->check();
        return true;
    }

    /**
     * Get body XML product info
     *
     * @param string $idProduct
     * @return string
     */
    private function getBodyXmlProductInfo(string $idProduct):string{
        $sessionKey = $this->webapiClient->getSessionKey();
        return sprintf('<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <getProductInfo xmlns="http://webapi.agrip.pl/">
                  <SessionKey>%s</SessionKey>
                  <Language>pl-PL</Language>
                  <Currency>PLN</Currency>
                  <ProductId>%s</ProductId>
                </getProductInfo>
              </soap:Body>
            </soap:Envelope>', $sessionKey, $idProduct);
    }
}
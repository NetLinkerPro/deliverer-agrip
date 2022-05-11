<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Archives;

use Exception;
use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\Contracts\DataProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NginxWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CategoryOperations;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use Symfony\Component\DomCrawler\Crawler;

class NginxDataProducts implements DataProducts
{
    use CrawlerHtml, ResourceRemember, XmlExtractor, LimitString, NumberExtractor, CleanerDescriptionHtml, ExtensionExtractor, CategoryOperations;

    /** @var NginxWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspDataProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(NginxWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
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
        $product = $this->fillProduct($product);
        if ($product) {
            yield $product;
        }
    }

    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addAttributesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {

        $ean = $product->getProperty('EAN');
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $sku = $this->getDataTable('Symbol', $crawlerProduct);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $url = $product->getProperty('bella_image');
        $main = sizeof($product->getImages()) === 0;
        $filenameUnique = $this->getFilenameUniqueImageProduct($url);
        $id = $filenameUnique;
        $product->addImage($main, $id, $url, $filenameUnique);
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addDescriptionProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $description = '<div class="description">';
        $descriptionWebsiteProduct = $this->getDescriptionWebsiteProduct($crawlerProduct);
        if ($descriptionWebsiteProduct) {
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionWebsiteProduct);
        }
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                if (!in_array(mb_strtolower($attribute->getName()), ['sku'])) {
                    $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
                }
            }
            $description .= '</ul></div>';
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get description webapi product
     *
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getDescriptionWebsiteProduct(Crawler $crawlerProduct): string
    {
        $contents = $this->getDataTable('Opis', $crawlerProduct, true);
        $crawlerDescription = $this->getCrawler($contents);
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
        $crawlerDescription->filter('a')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $descriptionWebsite = trim($crawlerDescription->filter('body')->html());
        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
        if (Str::startsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
        }
        if (Str::endsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceLast('<br>', '', $descriptionWebsite);
        }
        if ($descriptionWebsite) {
            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * Get product
     *
     * @param ProductSource $product
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function fillProduct(ProductSource $product): ?ProductSource
    {
        if (!$this->isValidImageUrl($product)){
            return null;
        }
        $crawlerProduct = $this->getCrawlerProduct($product);
        $this->addNameProduct($product, $crawlerProduct);
        $this->addImagesProduct($product, $crawlerProduct);
        $this->addAttributesProduct($product, $crawlerProduct);
        $this->addDescriptionProduct($product, $crawlerProduct);
        $this->removeSkuFromName($product);
        $product->removeLongAttributes();
        $product->check();
        return $product;
    }

    /**
     * Get crawler product
     *
     * @param ProductSource $product
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getCrawlerProduct(ProductSource $product): Crawler
    {
        $contents = $this->websiteClient->getContent($product->getUrl(), [
            'headers' => [
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'accept-encoding' => 'gzip, deflate, br',
                'accept-language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
            ]
        ]);
        return $this->getCrawler($contents);
    }

    /**
     * Get data basic
     *
     * @param Crawler $crawlerProduct
     * @param string $name
     * @return string
     */
    private function getDataBasic(Crawler $crawlerProduct, string $name): string
    {
        $value = '';
        $crawlerProduct->filter('table.dane-podstawowe tr')
            ->each(function (Crawler $trElementHtml) use (&$value, $name) {
                $tds = $trElementHtml->filter('td');
                if ($tds->count() === 2) {
                    $nameFound = $this->getTextCrawler($tds->eq(0));
                    if ($nameFound === sprintf('%s:', $name)) {
                        $value = $this->getTextCrawler($tds->eq(1));
                    }
                }
            });
        return $value;
    }

    /**
     * Get attribute
     *
     * @param string $name
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getAttribute(string $name, Crawler $crawlerProduct): string
    {
        $value = '';
        $crawlerProduct->filter('.product-attributes-ui > ul >li')
            ->each(function (Crawler $attributeElementLi) use (&$name, &$value) {
                $nameAttribute = $this->getTextCrawler($attributeElementLi->filter('.name-ui'));
                if (!$value && mb_strtolower($nameAttribute) === mb_strtolower($name)) {
                    $value = $this->getTextCrawler($attributeElementLi->filter('.value-ui'));
                }
            });
        return $value;
    }

    /**
     * Get category ID product
     *
     * @param string $href
     * @return string
     * @throws DelivererAgripException
     */
    private function getCategoryIdProduct(string $href): string
    {
        $hrefExplode = explode(',', $href);
        if (sizeof($hrefExplode) === 2) {
            throw new DelivererAgripException('Invalid ID category.');
        }
        return $hrefExplode[sizeof($hrefExplode) - 1];
    }

    /**
     * Get filename unique image product
     *
     * @param string $url
     * @return string
     * @throws DelivererAgripException
     */
    private function getFilenameUniqueImageProduct(string $url): string
    {
        if (!Str::contains($url, '.pl/')) {
            throw new DelivererAgripException('Url image not contains ".pl/".');
        }
        $explodeUrl = explode('.pl/', $url)[1];
        return str_replace(['/', ',', '-'], '', $explodeUrl);
    }

    /**
     * Add name product
     *
     * @param ProductSource $product
     * @param Crawler $crawlerProduct
     */
    private function addNameProduct(ProductSource $product, Crawler $crawlerProduct): void
    {
        $html = $crawlerProduct->html();
        $name = $this->getTextCrawler($crawlerProduct->filter('#karta-produktu td.ramka td.title'));
        $name = str_replace(';', '', $name);
        $product->setName($name);
    }

    /**
     * Get data table
     *
     * @param string $name
     * @param Crawler $crawlerProduct
     * @param bool $asHtml
     * @return string
     */
    private function getDataTable(string $name, Crawler $crawlerProduct, bool $asHtml = false): string
    {
        $value = '';
        $crawlerProduct->filter('td.metryczka > table > tr')
            ->each(function (Crawler $trElement) use (&$name, &$value, &$asHtml) {
                $tds = $trElement->filter('td');
                if ($tds->count() === 2) {
                    $foundName = $this->getTextCrawler($tds->eq(0));
                    if (Str::endsWith($foundName, ':')) {
                        $foundName = Str::replaceLast(':', '', $foundName);
                    }
                    if (!$value && mb_strtolower($name) === mb_strtolower($foundName)) {
                        if ($asHtml) {
                            $value = $tds->eq(1)->html();
                        } else {
                            $value = $this->getTextCrawler($tds->eq(1));
                            $value = trim(str_replace('&nbsp;', '', $value));
                        }
                    }
                } else if ($tds->count() > 2){
                    $foundName = $this->getTextCrawler($tds->eq(0));
                    if (Str::endsWith($foundName, ':')) {
                        $foundName = Str::replaceLast(':', '', $foundName);
                    }
                    if (!$value && mb_strtolower($name) === mb_strtolower($foundName)) {
                        $element = $tds->eq(1)->filter('p');
                        if (!$element->count()){
                            $element = $tds->eq(1)->filter('font');
                        }
                        if (!$element->count()){
                            $element = $tds->eq(1)->filter('table td');
                        }
                        if (!$element->count() || !$element->html()){
                            $tds->eq(1)->filter('table td')->each(function (Crawler $td) {
                                $tdValue = $td->html();
                                if (!$tdValue){
                                    foreach ($td as $node) {
                                        $node->parentNode->removeChild($node);
                                    }
                                }
                            });
                            $element = $tds->eq(1)->filter('table td');
                        }
                        if ($asHtml) {
                            $value = $element->html();
                            if (!$value){
                                $element = $tds->eq(1)->filter('.line_description');
                                $value = $element->html();
                            }
                        } else {
                            $value = $this->getTextCrawler($element);
                            $value = trim(str_replace('&nbsp;', '', $value));
                        }
                    }
                }
            });
        return preg_replace('/\s+/', ' ', $value);;
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
        $name = str_replace($sku, '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        $product->setName($name);
    }

    /**
     * Is valid image URL
     *
     * @param ProductSource $product
     * @return bool
     * @throws Exception
     */
    private function isValidImageUrl(ProductSource $product):bool
    {
        $imageUrl = $product->getProperty('bella_image');
        try {
            $this->websiteClient->getClientAnonymous()->head($imageUrl);
           return true;
        } catch (Exception $e){
            $code = $e->getCode() ?? 0;
            if ($code ===403){
                DelivererLogger::log(sprintf('Error URL image 403 %s.', $imageUrl));
                return false;
            }
            throw $e;
        }
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MagentoWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\TryCall;
use Symfony\Component\DomCrawler\Crawler;

class MagentoListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor, ExtensionExtractor, TryCall;

    const PER_PAGE = '10';

    /** @var MagentoWebsiteClient $websiteClient */
    protected $websiteClient;

    /**
     * SoapListProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(MagentoWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
    }

    /**
     * Get
     *
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     * @throws GuzzleException
     */
    public function get(?CategorySource $category = null): Generator
    {
        $products = $this->getProducts($category);
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get products
     *
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getProducts(?CategorySource $category): Generator
    {
        $urlCategory = $this->getUrlCategory($category);
        $crawlerPage = $this->getCrawlerPage($urlCategory);
        $products = $this->getProductsCrawlerPage($crawlerPage, $category);
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get URL category
     *
     * @param CategorySource|null $category
     * @return string
     */
    private function getUrlCategory(?CategorySource $category): string
    {
        $lastCategory = $category;
        while ($lastCategory->getChildren()) {
            $lastCategory = $lastCategory->getChildren()[0];
        }
        return $lastCategory->getUrl();
    }

    /**
     * Get product
     *
     * @param Crawler $containerProduct
     * @param CategorySource $category
     * @return ProductSource|null
     */
    private function getProduct(Crawler $containerProduct, CategorySource $category): ?ProductSource
    {
        $id = $this->getAttributeCrawler($containerProduct->filter('td.tbxCheckBox input'), 'value');
        $price = $this->getPrice($containerProduct);
        if (!$price) {
            return null;
        }
        $stock = $this->getStock($containerProduct);
        $tax = 23;
        $availability = 1;
        $url = sprintf('https://www.hurt.aw-narzedzia.com.pl/%s', $this->getAttributeCrawler($containerProduct->filter('td')->eq(2)->filter('a'), 'href'));
        $product = new ProductSource($id, $url);
        $product->setCategories([$category]);
        $product->setPrice($price);
        $product->setTax($tax);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $product->setTax(23);
        return $product;
    }

    /**
     * Get ID category
     *
     * @param CategorySource|null $category
     * @return string
     */
    private function getIdCategory(?CategorySource $category): string
    {
        $lastCategory = $category;
        while ($lastCategory->getChildren()) {
            $lastCategory = $lastCategory->getChildren()[0];
        }
        return $lastCategory->getId();
    }

    /**
     * Get products data page
     *
     * @param Crawler $crawlerPage
     * @param CategorySource $category
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsCrawlerPage(Crawler $crawlerPage, CategorySource $category): array
    {
        $sizes = $this->getSizes($crawlerPage);
        $parameters = $this->getParameters($crawlerPage);
        $name = $this->getNameProduct($crawlerPage);
        $description = $this->getDescriptionProduct($crawlerPage);
        $images = $this->getImagesProduct($crawlerPage);
        $products = [];
        foreach ($sizes as $size){
            $nameWithSize = $this->buildNameWithSize($name, $size);
            $product = new ProductSource($size['id'], $category->getUrl());
            $product->setName($nameWithSize);
            $product->setAvailability(1);
            $product->setTax(23);
            $product->setPrice($size['price']);
            $product->setStock($size['stock']);
            $product->setCategories([$category]);
            $this->addAttributesProduct($product, $size, $parameters);
            $this->addImagesProduct($product, $images);
            $this->addDescriptionProduct($product, $description);
            $product->removeLongAttributes();
            $product->check();
            array_push($products, $product);
        }
        return $products;
    }


    /**
     * Add attributes product
     *
     * @param ProductSource $product
     * @param array $size
     * @param array $parameters
     */
    private function addAttributesProduct(ProductSource $product, array $size, array $parameters): void
    {
        $sku = $size['id'];
        if ($sku) {
            $product->addAttribute('SKU', $sku, 200);
        }
        $attributesAdded = ['sku', 'ilość'];
        $unit = $size['attributes']['Jednostka']['value'] ?? '';
        if ($unit){
            $quantityValue = str_replace('.', ',', sprintf('%s %s', $size['in_packages'], $unit));
            $product->addAttribute('Ilość', $quantityValue, 250);
        }
        $order = 500;
        foreach ($size['attributes'] as $attribute){
            if (!in_array(mb_strtolower($attribute['name']), $attributesAdded)){
                $product->addAttribute($attribute['name'], $attribute['value'], $order);
                array_push($attributesAdded, mb_strtolower($attribute['name']));
                $order += 50;
            }
        }
        foreach ($parameters as $attributeName => $attributeValue){
            if (!in_array(mb_strtolower($attributeName), $attributesAdded)){
                $product->addAttribute($attributeName, $attributeValue, $order);
                array_push($attributesAdded, mb_strtolower($attributeName));
                $order += 50;
            }
        }
    }

    /**
     * Add description product
     *
     * @param ProductSource $product
     * @param string $descriptionHtml
     * @throws DelivererAgripException
     */
    private function addDescriptionProduct(ProductSource $product, string $descriptionHtml): void
    {
        $description = '<div class="description">';
        $attributes = $product->getAttributes();
        if ($attributes) {
            $description .= '<div class="attributes-section-description" id="description_extra3"><ul>';
            foreach ($attributes as $attribute) {
                $description .= sprintf('<li>%s: <strong>%s</strong></li>', $attribute->getName(), $attribute->getValue());
            }
            $description .= '</ul></div>';
        }
        if ($descriptionHtml) {
            $description .= sprintf('<div class="content-section-description" id="description_extra4">%s</div>', $descriptionHtml);
        }
        $description .= '</div>';
        $product->setDescription($description);
    }

    /**
     * Get crawler page
     *
     * @param string $url
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(string $url): Crawler
    {
        sleep(1);
        $contents =$this->tryCall(function() use (&$url){
            return  $this->websiteClient->getContents($url);
        });
        return $this->getCrawler($contents);
    }

    /**
     * Get data price
     *
     * @param Crawler $containerProduct
     * @return float
     */
    private function getPrice(Crawler $containerProduct): float
    {
        $text = $this->getTextCrawler($containerProduct->filter('td')->eq(7));
        $text = str_replace([' ', ';&nbsp;', 'PLN'], '', $text);
        $text = str_replace(',', '.', $text);
        return $this->extractFloat($text);
    }

    /**
     * Get quantity pages
     *
     * @param Crawler $crawlerFirstPage
     * @return int
     * @throws DelivererAgripException
     */
    private function getQuantityPages(Crawler $crawlerFirstPage): int
    {
        $quantityPages = 1;
        $text = $this->getTextCrawler($crawlerFirstPage->filter('#srodkowoPrawaKolumna h2.naglowek i'));
        if (Str::contains($text, ' z ')) {
            $text = explode(' z ', $text)[1];
            $text = explode(' wysz', $text)[0];
            $quantityPages = (int)ceil(((int)$text) / self::PER_PAGE);
        }
        if (!$quantityPages) {
            throw new DelivererAgripException('Incorrect quantity pages.');
        }
        return $quantityPages;
    }

    /**
     * Get stock
     *
     * @param Crawler $containerProduct
     * @return int
     * @throws DelivererAgripException
     */
    private function getStock(Crawler $containerProduct): int
    {
        $text = $this->getAttributeCrawler($containerProduct->filter('td')->eq(5)->filter('span'), 'class');
        if ($text === 'stanDuzo') {
            return 20;
        } else if ($text === 'stanSrednio') {
            return 10;
        } else if ($text === 'stanMalo') {
            return 3;
        } else if ($text === 'stanBrak') {
            return 0;
        }
        throw new DelivererAgripException('Not detect stock.');
    }

    /**
     * Get unit
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getUnit(Crawler $containerProduct): string
    {
        $text = $this->getAttributeCrawler($containerProduct->filter('td')->eq(8)->filter('span'), 'class');
        return str_replace([' ', ';&nbsp;'], '', $text);
    }

    /**
     * Get sizes
     *
     * @param Crawler $crawlerPage
     * @return array
     */
    private function getSizes(Crawler $crawlerPage): array
    {
        $sizes = [];
        $crawlerPage->filter('table.grouped-items-table.sortable tbody tr')
            ->each(function (Crawler $trElement) use (&$sizes, &$crawlerPage) {
                $sizeData = $this->getSizeData($trElement);
                $packages = $this->getPackages($trElement, $crawlerPage);
                foreach ($packages as $package){
                    $sizes[$package['id']] = $package;
                    $sizes[$package['id']]['attributes'] = $sizeData;
                }
            });
        return $sizes;
    }

    /**
     * Get size data
     *
     * @param Crawler $trElement
     * @return array
     */
    private function getSizeData(Crawler $trElement): array
    {
        $sizeData = [];
        $beforeNumberArticle = true;
        $trElement->filter('td')
            ->each(function (Crawler $tdElementOriginal) use (&$sizeData, &$beforeNumberArticle) {
                $name = trim(Str::replaceLast(':', '', $this->getTextCrawler($tdElementOriginal->filter('span.hidden-small'))));
                if ($name) {
                    $tdElement = $this->getCrawler($tdElementOriginal->outerHtml());
                    $tdElement->filter('span.hidden-small')->each(function (Crawler $crawler) {
                        foreach ($crawler as $node) {
                            $node->parentNode->removeChild($node);
                        }
                    });
                    if ($name ==='Nr artykułu'){
                        $beforeNumberArticle = false;
                    }
                    $name = str_replace('Nr artykułu', 'Numer katalogowy', $name);
                    $name = str_replace('Jednostka miary', 'Jednostka', $name);
                    $name = str_replace('Wielkość pakownia', 'Wielkość opakowania', $name);
                    $name = str_replace('Wielkość opakownia', 'Wielkość opakowania', $name);
                    $name = str_replace('~ Sztuk w 1 kg', 'Sztuk w 1 kg ~', $name);

                    if (!in_array($name, ['Stan magazynowy', 'Wielkość opakowania', 'Cena jednostkowa po rabatach', 'Cena opakowania po rabatach', 'Ilość'])) {
                        $value = $this->getTextCrawler($tdElement);
                        $value = str_replace('n/a', '', $value);
                        if ($value) {
                            if ($name === 'Jednostka'){
                                $value = mb_strtolower($value);
                                if ($value === 'szt'){
                                    $value .= '.';
                                }
                            }
                            $sizeData[$name] =[
                                'name' => $name,
                                'value' =>$value,
                                'before_number_article' => $beforeNumberArticle
                            ];
                        }
                    }
                }
            });
        return $sizeData;
    }

    /**
     * Get packages
     *
     * @param Crawler $trElement
     * @param Crawler $crawlerPage
     * @return array
     * @throws DelivererAgripException
     */
    private function getPackages(Crawler $trElement, Crawler $crawlerPage): array
    {
        $html = $trElement->html();
        if (Str::contains($html, 'Q9237')){
            echo "";
        }
        $packages = [];
        $elements = $this->getTdElement('Cena opakowania po rabatach', $trElement)->filter('span');
        $elements->each(function(Crawler $spanElement) use (&$packages, &$trElement, &$crawlerPage){
            $idSpan = $this->getAttributeCrawler($spanElement, 'id');
            if (Str::startsWith($idSpan, 'product-price-')){
                $idArticle = $this->getIdArticle($trElement);
                $idVariant = str_replace('product-price-', '', $idSpan);
                if (!$idVariant){
                    throw new DelivererAgripException('Not found ID');
                }
                $id = sprintf('%s_%s', $idArticle, $idVariant);
                $stockSpan = $trElement->filter(sprintf('span#stock_%s', $id));
                if ($stockSpan->count()){
                    $stock = (int) $this->getTextCrawler($stockSpan);
                } else {
                    $stock = (int) $this->getTextCrawler($this->getTdElement('Stan magazynowy', $trElement));
                }
                $inPackage = $this->getInPackage($trElement, $idArticle, $idVariant);
                $price = $this->getProductPrice($idArticle, $idVariant, $trElement);
                $minimumQuantity = $this->getMinimumQuantityProduct($trElement, $crawlerPage);
                if ($minimumQuantity > 1){
                    if ($inPackage > 1){
                        return null;
                    }
                    $inPackage = $minimumQuantity;
                    $price = round($price * $minimumQuantity, 2);
                    $stock = intval($stock / $minimumQuantity);
                }
                $packages[$id] = [
                    'id' => $id,
                    'stock' => $stock,
                    'in_packages' => $inPackage,
                    'price' =>$price,
                ];
            }
        });
        return $packages;
    }

    /**
     * Get td element
     *
     * @param string $name
     * @param Crawler $trElement
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getTdElement(string $name, Crawler $trElement):Crawler{
        $foundTdElement = null;
        $trElement->filter('td')
            ->each(function(Crawler $tdElement) use (&$foundTdElement, &$name){
               $foundName = mb_strtolower(trim(str_replace(':', '', $this->getTextCrawler($tdElement->filter('span.hidden-small')))));
               $foundName = str_replace('wielkość pakownia', 'wielkość opakowania', $foundName);
                $foundName = str_replace('wielkość opakownia', 'wielkość opakowania', $foundName);
               if (!$foundTdElement && mb_strtolower($name)=== $foundName){
                   $foundTdElement = $this->getCrawler($tdElement->outerHtml());
               }
            });
        if ($foundTdElement === null){
            throw new DelivererAgripException(sprintf('Not found td element %s.', $name));
        }
        $foundTdElement->filter('span.hidden-small')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        return $foundTdElement;
    }

    /**
     * Get product price
     *
     * @param string $idArticle
     * @param string $idVariant
     * @param Crawler $trElement
     * @return float
     */
    private function getProductPrice(string $idArticle, string $idVariant, Crawler $trElement): float
    {
        $textPrice = $this->getTextCrawler($trElement->filter(sprintf('#price_%s_%s #product-price-%s', $idArticle, $idVariant, $idVariant)));
        if ($textPrice === null){
            $textPrice = $this->getTextCrawler($trElement->filter(sprintf('#product-price-%s', $idVariant)));
        }
        $textPrice = str_replace([' ', ' ', '&nbsp;', 'zł', '.'], '', $textPrice);
        $textPrice = str_replace(',', '.', $textPrice);
        return $this->extractFloat($textPrice);
    }

    /**
     * Get parameters
     *
     * @param Crawler $crawlerPage
     * @return array
     */
    private function getParameters(Crawler $crawlerPage): array
    {
        $parameters = [];
        $crawlerPage->filter('#product-attribute-specs-table tbody tr')
            ->each(function(Crawler $trElement) use (&$parameters){
                $name = $this->getTextCrawler($trElement->filter('th'));
                $name = str_replace('Jednostka Sprzedaży', 'Jednostka', $name);
                $value = $this->getTextCrawler($trElement->filter('td'));
                if (!in_array($name,['Nr Art.', 'Oznaczenia', 'Certyfikat', 'Wielkość Opakowania w JM']) && $name && $value && $value !== 'n/a'){
                    if ($name === 'Jednostka'){
                        $value = mb_strtolower($value);
                        if ($value === 'szt'){
                            $value .= '.';
                        }
                    }
                    $parameters[$name] = $value;
                }
            });
        return $parameters;
    }

    /**
     * Get name product
     *
     * @param Crawler $crawlerPage
     * @return string
     */
    private function getNameProduct(Crawler $crawlerPage): string
    {
        return $this->getTextCrawler($crawlerPage->filter('.product-name h1'));
    }

    /**
     * Get descirption product
     *
     * @param Crawler $crawlerPage
     * @return string
     */
    private function getDescriptionProduct(Crawler $crawlerPage): string
    {
        $crawlerDescription = $crawlerPage->filter('div.short-description');
        if (!$crawlerDescription->count()) {
            return '';
        }
        $crawlerDescription->filter('h2')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $crawlerDescription->filter('h3')->each(function (Crawler $crawler) {
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
        $crawlerDescription->filter('p')->each(function (Crawler $crawler) {
            $html = $crawler->html();
            if (Str::contains($html, 'Produkt sprzedawany w opakowaniach zbiorczych') || Str::contains($html, 'Dokumenty dostępne do pobrania') || Str::contains($html, 'Podane parametry mogą ulec zmianie w zależności')){
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                }
            }
        });
        $descriptionWebsite = trim($crawlerDescription->html());
        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
        if (Str::startsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
        }
        if (Str::endsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceLast('<br>', '', $descriptionWebsite);
        }
        if ($descriptionWebsite) {
//            $descriptionWebsite = $this->cleanAttributesHtml($descriptionWebsite);
//            $descriptionWebsite = $this->cleanEmptyTagsHtml($descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * Get images product
     *
     * @param Crawler $crawlerPage
     * @return array
     */
    private function getImagesProduct(Crawler $crawlerPage): array
    {
        $identifierProducts = $this->getTextCrawler($crawlerPage->filter('tr.sku td'));
        $addedImages = [];
        $images = [];
        $crawlerPage->filter('div.product-img-box a[rel="lighbox-zoom-gallery"]')
            ->each(function (Crawler $aElement) use (&$addedImages, &$images, &$identifierProducts) {
                $url = $this->getAttributeCrawler($aElement, 'href');
                $main = sizeof($images) === 0;
                $url = str_replace('https://www.simple24.pl/media/catalog/product/cache/1/image/', '', $url);
                $url = explode('/', $url, 2)[1];
                $url = sprintf('https://www.simple24.pl/media/catalog/product/%s', $url);
                $extension = $this->extractExtension($url, 'png');
                $filenameUnique = sprintf('%s_%s.%s', $identifierProducts, sizeof($images) + 1, $extension);
                $id = $filenameUnique;
                if (!in_array($url, $addedImages)) {
                    array_push($addedImages, $url);
                    array_push($images, [
                       'main' =>$main,
                       'url' =>$url,
                       'filename_unique' =>$filenameUnique,
                       'id' =>$id,
                    ]);
                }
            });
        return $images;
    }

    /**
     * Build name with size
     *
     * @param string $name
     * @param array $size
     * @return string
     */
    private function buildNameWithSize(string $name, array $size): string
    {
        $attributes = $size['attributes'];
        foreach ($attributes as $attribute){
            if (!Str::contains($name, $attribute['value']) && $attribute['before_number_article']){
                $name .= sprintf(' %s', $attribute['value']);
            }
        }
        $unit = $size['attributes']['Jednostka']['value']??null;
        if ($unit){
            $name .= sprintf(' %s%s', str_replace('.', ',', $size['in_packages']), $unit);
        }
        return $name;
    }

    /**
     * Get minimum quantity product
     *
     * @param Crawler $trElement
     * @param Crawler $crawlerPage
     * @return int
     */
    private function getMinimumQuantityProduct(Crawler $trElement, Crawler $crawlerPage): int
    {
        $minimumQuantity = (int) $this->getAttributeCrawler($trElement->filter('td.items_qty'), 'data-opakowanie_zbiorcze');
        $descriptionCrawler = $crawlerPage->filter('div.short-description');
         if ($descriptionCrawler->count()){
           $html = $descriptionCrawler->html();
           if (!Str::contains($html, 'Produkt sprzedawany w opakowaniach zbiorczych')){
               $minimumQuantity = 1;
           }
       } else {
             $minimumQuantity = 1;
         }
        $name = $this->getTextCrawler($crawlerPage->filter('tr.wielkosc_opakowania th'));
        if ($name === 'Wielkość Opakowania w JM'){
            $minimumQuantity = (int) $this->getTextCrawler($crawlerPage->filter('tr.wielkosc_opakowania td'));
        }
        if (!$minimumQuantity){
            $minimumQuantity = 1;
        }
        return $minimumQuantity;
    }

    /**
     * Add images product
     *
     * @param ProductSource $product
     * @param array $images
     * @throws DelivererAgripException
     */
    private function addImagesProduct(ProductSource $product, array $images): void
    {
        foreach ($images as $image){
            $product->addImage($image['main'], $image['id'], $image['url'], $image['filename_unique']);
        }
    }

    /**
     * Get in package
     *
     * @param Crawler $trElement
     * @param string $idArticle
     * @param string $idVariant
     * @return float
     * @throws DelivererAgripException
     */
    private function getInPackage(Crawler $trElement, string $idArticle = null, string $idVariant = null): float
    {
        $inPackage = null;
        if ($idArticle && $idVariant){
            $inPackage = (float) $this->getTextCrawler($this->getTdElement('Wielkość opakowania', $trElement)->filter(sprintf('select#%s option[value="%s"]', $idArticle, $idVariant)));
        }
        if (!$inPackage){
            return (float) $this->getTextCrawler($this->getTdElement('Wielkość opakowania', $trElement));
        }
        return $inPackage;
    }

    /**
     * Get ID article
     *
     * @param Crawler $trElement
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdArticle(Crawler $trElement): string
    {
        $html = $trElement->html();
        $crawler = $this->getCrawler($html);
        $tdElement = $this->getTdElement('Nr artykułu', $crawler);
        return $this->getTextCrawler($tdElement);
    }
}
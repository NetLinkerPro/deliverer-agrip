<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\DotnetnukeListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\DotnetnukeWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MagresnetWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\WideStore\Sections\Categories\Models\Category;
use Symfony\Component\DomCrawler\Crawler;

class DotnetnukeListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor;

    /** @var DotnetnukeWebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var DotnetnukeListCategories $listCategories */
    protected $listCategories;

    /**
     * SoapListProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(DotnetnukeWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
        $this->listCategories = app(DotnetnukeListCategories::class, [
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
     * @throws DelivererAgripException
     */
    public function get(?CategorySource $category = null): Generator
    {
        $categories = $this->listCategories->get();
        foreach ($categories as $category) {
            $deepestCategory = $this->getDeepestCategory($category);
            $products = $this->getProducts($category, $deepestCategory);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    private function getProducts(CategorySource $category, CategorySource $deepestCategory): Generator
    {
        $crawlerPageProducts = $this->getCrawlerPageProducts($deepestCategory);
        $products = $this->getProductsCrawlerPage($category, $crawlerPageProducts);
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get product
     *
     * @param Crawler $containerProduct
     * @return ProductSource|null
     * @throws DelivererAgripException
     */
    private function getProduct(Crawler $containerProduct, CategorySource $category): ?ProductSource
    {
        $dataId = $this->getAttributeCrawler($containerProduct, 'data');
        $crawlerDetailProduct = $this->getCrawlerDetailProduct($dataId, $containerProduct);
        $id = $this->getIdProduct($crawlerDetailProduct);
        $infoPrice = $this->getInfoPrice($crawlerDetailProduct);
        $price = $infoPrice['price_netto'];
        if (!$price) {
            return null;
        }
        $stock = $infoPrice['quantity'];
        $availability = 1;
        $url = 'https://www.argip.com.pl/Produkty/Zakupy.aspx?data_id=' . $dataId;
        $product = new ProductSource($id, $url);
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setAvailability($availability);
        $product->addCategory($category);
        $product->setTax(23);

        $product->setProperty('unit', 'szt.');
        $name = $this->getName($crawlerDetailProduct, $infoPrice);
        $product->setName($name);
        $sku = $this->getTrExtra('Indeks', $crawlerDetailProduct);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 50);
        }
        $ean = $this->getTrExtra('Podst. kod ean', $crawlerDetailProduct);
        if ($ean) {
            $product->addAttribute('EAN', $ean, 100);
        }
        $product->setDescription('');
        $product->check();
        return $product;
    }

    /**
     * Get details
     *
     * @param ProductSource $product
     * @return ProductSource|null
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getDetails(ProductSource $product): ?ProductSource
    {
        $detailPageCrawler = $this->getDetailPageCrawler($product);
        if (!$this->addProductName($detailPageCrawler, $product)) {
            return null;
        }
        DelivererLogger::log(sprintf('OK %s.', $product->getId()));
        $this->addProductTax($detailPageCrawler, $product);
        $this->addProductAttributes($detailPageCrawler, $product);
        $this->addDescriptionProduct($detailPageCrawler, $product);
        $this->addImageProduct($detailPageCrawler, $product);
        $this->addCategoryProduct($detailPageCrawler, $product);
        $product->check();
        return $product;
    }

    /**
     * Get detail page crawler
     *
     * @param CategorySource $category
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getDetailPageCrawler(ProductSource $product): Crawler
    {
        DelivererLogger::log(sprintf('Get product detail %s %s %s.', $product->getProperty('sku'), $product->getStock(), $product->getPrice()));
        $dataAjax = $this->websiteClient->getLastDataAspx();
        $contents = $this->websiteClient->getContentAjax('http://212.180.197.238/OfertaMobile.aspx', [
            '_' => [
                'old_data_aspx' => true,
            ],
            RequestOptions::FORM_PARAMS => [
                'ctl00$ToolkitScriptManager1' => sprintf('ctl00$ContentPlaceHolder1$UpdatePanel1|%s', $product->getProperty('name_button')),
                'ctl00$ContentPlaceHolder1$ddlMagazyn' => '3',
                'ctl00$ContentPlaceHolder1$ddlCeny' => '5',
                'ctl00$ContentPlaceHolder1$ddlKategoria' => '0',
                'ctl00$ContentPlaceHolder1$txtName' => '',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl02$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl03$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl04$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl05$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl06$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl07$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl08$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl09$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl10$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl11$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl12$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl13$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$hfPageIndex' => '0',
                'ctl00$ContentPlaceHolder1$hfPageSize' => '60',
                'ctl00$ContentPlaceHolder1$hfProducent' => '0',
                'ctl00$ContentPlaceHolder1$hfAsortyment' => '5',
                'ctl00$ContentPlaceHolder1$hfOfrid' => '0',
                'ctl00$ContentPlaceHolder1$hfHanId' => '0',
                'ctl00$ContentPlaceHolder1$hfImage' => '0',
                'ctl00$ContentPlaceHolder1$hfOrderId' => '0',
                'ctl00$ContentPlaceHolder1$hfKntId' => '23723',
                'ctl00$ContentPlaceHolder1$hfDkrId' => '35',
                'hiddenInputToUpdateATBuffer_CommonToolkitScripts' => '1',
                '__EVENTTARGET' => $dataAjax['event_target'],
                '__EVENTARGUMENT' => $dataAjax['event_argument'],
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAjax['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAjax['view_state_generator'],
                '__PREVIOUSPAGE' => $dataAjax['previous_page'],
                '__EVENTVALIDATION' => $dataAjax['event_validation'],
                '__VIEWSTATEENCRYPTED' => $dataAjax['view_state_encrypted'],
                '__ASYNCPOST' => 'true',
                sprintf('%s.x', $product->getProperty('name_button')) => '0',
                sprintf('%s.y', $product->getProperty('name_button')) => '0',
            ]
        ]);
        return $this->getCrawler($contents);
    }

    /**
     * Get crawler next page
     *
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerNextPage(int $page): Crawler
    {
        DelivererLogger::log(sprintf('Get data page %s.', $page));
        $dataAjax = $this->websiteClient->getLastDataAspx();
        $contents = $this->websiteClient->getContentAjax('http://212.180.197.238/OfertaMobile.aspx', [
            RequestOptions::FORM_PARAMS => [
                'ctl00$ToolkitScriptManager1' => 'ctl00$ContentPlaceHolder1$UpdatePanel1|ctl00$ContentPlaceHolder1$gvTowary$ctl15$btnGvTowaryNextPage',
                'ctl00$ContentPlaceHolder1$ddlMagazyn' => '3',
                'ctl00$ContentPlaceHolder1$ddlCeny' => '5',
                'ctl00$ContentPlaceHolder1$ddlKategoria' => '0',
                'ctl00$ContentPlaceHolder1$txtName' => '',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl02$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl03$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl04$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl05$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl06$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl07$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl08$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl09$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl10$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl11$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl12$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl13$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$hfPageIndex' => '0',
                'ctl00$ContentPlaceHolder1$hfPageSize' => '60',
                'ctl00$ContentPlaceHolder1$hfProducent' => '0',
                'ctl00$ContentPlaceHolder1$hfAsortyment' => '0',
                'ctl00$ContentPlaceHolder1$hfOfrid' => '0',
                'ctl00$ContentPlaceHolder1$hfHanId' => '0',
                'ctl00$ContentPlaceHolder1$hfImage' => '0',
                'ctl00$ContentPlaceHolder1$hfOrderId' => '249209',
                'ctl00$ContentPlaceHolder1$hfKntId' => '23723',
                'ctl00$ContentPlaceHolder1$hfDkrId' => '35',
                'hiddenInputToUpdateATBuffer_CommonToolkitScripts' => '1',
                '__EVENTTARGET' => $dataAjax['event_target'],
                '__EVENTARGUMENT' => $dataAjax['event_argument'],
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAjax['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAjax['view_state_generator'],
                '__PREVIOUSPAGE' => $dataAjax['previous_page'],
                '__EVENTVALIDATION' => $dataAjax['event_validation'],
                '__VIEWSTATEENCRYPTED' => $dataAjax['view_state_encrypted'],
                '__ASYNCPOST' => 'true',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl15$btnGvTowaryNextPage.x' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl15$btnGvTowaryNextPage.y' => '0',
            ]
        ]);
        return $this->getCrawler($contents);
    }

    /**
     * Get products data page
     *
     * @param Crawler $crawlerPage
     * @return array
     * @throws DelivererAgripException
     */
    private function getProductsCrawlerPage(CategorySource $category, Crawler $crawlerPage): array
    {
        $products = [];
        $crawlerPage->filter('td.cclick')->each(function (Crawler $td) use (&$products, &$category) {
            $product = $this->getProduct($td, $category);
            if ($product) {
                array_push($products, $product);
            }
        });
        return $products;
    }

    /**
     * Get crawler first page
     *
     * @param CategorySource|null $category
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPageProducts(CategorySource $deepestCategory): Crawler
    {
        DelivererLogger::log(sprintf('Get products from category %s', $deepestCategory->getId()));
        $contents = $this->websiteClient->getContentAjax('https://www.argip.com.pl/Produkty/Zakupy.aspx', [
            RequestOptions::FORM_PARAMS => [
                'ctx' => '13',
                '__DNNCAPISCI' => 'dnn$ctr418$ArgipTree',
                '__DNNCAPISCP' => '%7B%22method%22%3A%22GetLeaf%22%2C%22args%22%3A%7B%22itemid%22%3A%22' . $deepestCategory->getId() . '%22%7D%7D',
                '__DNNCAPISCT' => '2'
            ]
        ]);
        $contents = explode('<textarea id="txt">', $contents)[1];
        $contents = explode('</textarea>', $contents)[0];
        $contents = html_entity_decode($contents);
        $dataCategories = json_decode($contents, true);
        $dataCategories = json_decode($dataCategories['d'] ?? '{}', true);
        return $this->getCrawler($dataCategories['result'] ?? '');
    }

    /**
     * Get data price
     *
     * @param Crawler $containerProduct
     * @return float
     * @throws DelivererAgripException
     */
    private function getPrice(Crawler $containerProduct): float
    {
        $text = $this->getTextByPartId('_ofr_cena', 'span', $containerProduct);
        $text = str_replace([' ', ','], '', $text);
        $price = $this->extractFloat($text);
        if ($price === null) {
            throw new DelivererAgripException('Not found price.');
        }
        return $price;
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
        $text = $this->getTextCrawler($crawlerFirstPage->filter('#ctl00_ContentPlaceHolder1_gvTowary_ctl15_lblcp'));
        $text = explode('/', $text)[1] ?? '';
        $number = (int)$text;
        if ($number > $quantityPages) {
            $quantityPages = $number;
        }
        if ($quantityPages < 400) {
            throw new DelivererAgripException('Invalid quantity pages.');
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
        $text = $this->getAttributeByPartId('_ImgStan', 'img', 'src', $containerProduct);
        if (Str::contains($text, 'status5')) {
            $stock = 6;
        } else if (Str::contains($text, 'status4')) {
            $stock = 4;
        } else if (Str::contains($text, 'status3')) {
            $stock = 3;
        } else if (Str::contains($text, 'status2')) {
            $stock = 2;
        } else if (Str::contains($text, 'status1')) {
            $stock = 1;
        } else {
            $stock = 0;
        }
        return $stock;
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
     * Get ID product
     *
     * @param Crawler $containerProduct
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdProduct(Crawler $containerProduct): string
    {
        $id = $this->getTrExtra('ID produktu', $containerProduct);
        if (!$id) {
            throw new DelivererAgripException('Not found ID product.');
        }
        return $id;
    }

    /**
     * Get SKU product
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getSkuProduct(Crawler $containerProduct): string
    {
        $sku = $this->getTextByPartId('_Label10', 'span', $containerProduct);
        $sku = str_replace(' ', ' ', $sku);
        return trim($sku);
    }

    /**
     * Get text by part ID
     *
     * @param string $partId
     * @param string $selector
     * @param Crawler $containerProduct
     * @return string
     */
    private function getTextByPartId(string $partId, string $selector, Crawler $containerProduct): string
    {
        $text = '';
        $containerProduct->filter($selector)
            ->each(function (Crawler $element) use (&$text, &$partId) {
                if (!$text) {
                    $idElement = $this->getAttributeCrawler($element, 'id');
                    if (Str::contains($idElement, $partId)) {
                        $text = $this->getTextCrawler($element);
                    }
                }
            });
        return $text;
    }

    /**
     * Get attribute by part ID
     *
     * @param string $partId
     * @param string $selector
     * @param string $attribute
     * @param Crawler $containerProduct
     * @return string
     */
    private function getAttributeByPartId(string $partId, string $selector, string $attribute, Crawler $containerProduct): string
    {
        $text = '';
        $containerProduct->filter($selector)
            ->each(function (Crawler $element) use (&$text, &$partId, &$attribute) {
                if (!$text) {
                    $idElement = $this->getAttributeCrawler($element, 'id');
                    if (Str::contains($idElement, $partId)) {
                        $text = $this->getAttributeCrawler($element, $attribute);
                    }
                }
            });
        return $text;
    }

    /**
     * Get name button
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getNameButton(Crawler $containerProduct): string
    {
        return $this->getAttributeByPartId('_ImBtnSelect', 'input', 'name', $containerProduct);
    }

    /**
     * Add product name
     *
     * @param Crawler $detailPageCrawler
     * @param ProductSource $product
     * @return bool
     */
    private function addProductName(Crawler $detailPageCrawler, ProductSource $product): bool
    {
        $sku = $this->getParameterProduct('Symbol', $detailPageCrawler);
        $sku = str_replace(' ', ' ', $sku);
        if ($sku !== $product->getProperty('sku')) {
            DelivererLogger::log('Invalid SKU.');
            return false;
        }
        $name = $this->getParameterProduct('Nazwa', $detailPageCrawler);
        $name = str_replace('"', '', $name);
        $product->setName($name);
        return true;
    }

    /**
     * Get parameter product
     *
     * @param string $key
     * @param Crawler $detailPageCrawler
     * @return string
     */
    private function getParameterProduct(string $key, Crawler $detailPageCrawler, bool $asHtml = false): string
    {
        $value = '';
        $detailPageCrawler->filter('#ctl00_ContentPlaceHolder1_dvTowar > tr')
            ->each(function (Crawler $tr) use (&$value, &$key, &$asHtml) {
                $tds = $tr->filter('td');
                if ($tds->count() === 2) {
                    $foundKey = $this->getTextCrawler($tds->eq(0));
                    if (!$asHtml) {
                        $foundValue = $this->getTextCrawler($tds->eq(1));
                    } else {
                        $foundValue = $tds->eq(1)->html();
                    }
                    $foundValue = str_replace(' ', ' ', $foundValue);
                    $foundValue = trim($foundValue);
                    if (!$value && $foundKey === $key) {
                        $value = $foundValue;
                    }
                }
            });
        return $value;
    }

    /**
     * Add product tax
     *
     * @param Crawler $detailPageCrawler
     * @param ProductSource $product
     */
    private function addProductTax(Crawler $detailPageCrawler, ProductSource $product): void
    {
        $tax = $this->getParameterProduct('Stawka vat', $detailPageCrawler);
        $tax = (int)$tax;
        if (!$tax) {
            $tax = 23;
        }
        $product->setTax($tax);
    }

    /**
     * Add attributes product
     *
     * @param Crawler $detailPageCrawler
     * @param ProductSource $product
     */
    private function addProductAttributes(Crawler $detailPageCrawler, ProductSource $product): void
    {
        $sku = $this->getParameterProduct('Symbol', $detailPageCrawler);
        $sku = str_replace(' ', ' ', $sku);
        $unit = $this->getParameterProduct('Jednostka', $detailPageCrawler);
        if ($sku) {
            $product->addAttribute('SKU', $sku, 50);
        }
        if ($unit) {
            $product->addAttribute('Jednostka', $unit, 75);
        }
    }

    /**
     * Add description product
     *
     * @param Crawler $crawlerProduct
     * @param ProductSource $product
     */
    private function addDescriptionProduct(Crawler $crawlerProduct, ProductSource $product): void
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
     * @param Crawler $crawlerProduct
     * @return string
     */
    private function getDescriptionWebsiteProduct(Crawler $crawlerProduct): string
    {
        $html = $this->getParameterProduct('Opis', $crawlerProduct, 'true');
        if (!$html) {
            return '';
        }
        $crawlerDescription = $this->getCrawler($html);
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
        $descriptionWebsite = trim($crawlerDescription->filter('body')->html());
        $descriptionWebsite = str_replace(['<br><br><br>', '<br><br>'], '<br>', $descriptionWebsite);
        if (Str::startsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceFirst('<br>', '', $descriptionWebsite);
        }
        if (Str::endsWith($descriptionWebsite, '<br>')) {
            $descriptionWebsite = Str::replaceLast('<br>', '', $descriptionWebsite);
        }
        return $descriptionWebsite;
    }

    /**
     * Add image product
     *
     * @param Crawler $detailPageCrawler
     * @param ProductSource $product
     * @throws DelivererAgripException
     */
    private function addImageProduct(Crawler $detailPageCrawler, ProductSource $product): void
    {
        $image = $this->getParameterProduct('Zdjęcie', $detailPageCrawler);
        if ($image) {
            $id = explode('.', $image)[0];
            $url = sprintf('http://212.180.197.238/ImageFromOferta.ashx?ID=%s', $id);
            if ($id) {
                $product->addImage(true, $id, $url, $image);
            }
        }
    }

    /**
     * Add category product
     *
     * @param Crawler $detailPageCrawler
     * @param ProductSource $product
     * @throws DelivererAgripException
     */
    private function addCategoryProduct(Crawler $detailPageCrawler, ProductSource $product): void
    {
        $group = $this->getParameterProduct('Grupa', $detailPageCrawler);
        if (!$group) {
            throw new DelivererAgripException('Not found group.');
        }
        $category = new CategorySource($group, $group, 'http://212.180.197.238/OfertaMobile.aspx');
        $product->setCategories([$category]);
    }

    /**
     * Get deepest category
     *
     * @param CategorySource $category
     * @return CategorySource
     */
    private function getDeepestCategory(CategorySource $category): CategorySource
    {
        $categoryDeepest = $category;
        while ($categoryDeepest) {
            $categoryChild = $categoryDeepest->getChildren()[0] ?? null;
            if ($categoryChild) {
                $categoryDeepest = $categoryChild;
            } else {
                break;
            }
        }
        return $categoryDeepest;
    }

    private function getCrawlerDetailProduct($dataId, Crawler $containerProduct)
    {
        DelivererLogger::log(sprintf('Get detail product %s', $dataId));
        $contents = $this->websiteClient->getContentAjax('https://www.argip.com.pl/Produkty/Zakupy.aspx', [
            RequestOptions::FORM_PARAMS => [
                'ctx' => '38',
                '__DNNCAPISCI' => 'dnn$ctr418$ArgipTree',
                '__DNNCAPISCP' => '%7B%22method%22%3A%22GetDetails%22%2C%22args%22%3A%7B%22pozid%22%3A%22' . $dataId . '%22%7D%7D',
                '__DNNCAPISCT' => '2'
            ]
        ]);
        $contents = explode('<textarea id="txt">', $contents)[1];
        $contents = explode('</textarea>', $contents)[0];
        $contents = html_entity_decode($contents);
        $dataCategories = json_decode($contents, true);
        $dataCategories = json_decode($dataCategories['d'] ?? '{}', true);
        return $this->getCrawler($dataCategories['result'] ?? '');
    }

    private function getTrExtra(string $key, Crawler $containerProduct): string
    {
        $tr = null;
        $containerProduct->filter('tr')->each(function (Crawler $trFound) use (&$tr) {
            $idElement = $this->getAttributeCrawler($trFound, 'id');
            if (Str::startsWith($idElement, 'trExtra_')) {
                $tr = $trFound;
            }
        });
        $value = '';
        $tr->filter('div > div')->each(function (Crawler $div) use ($key, &$value) {
            $spans = $div->filter('span');
            if ($spans->count() == 2) {
                $keyFound = $this->getTextCrawler($spans->eq(0));
                $keyFound = str_replace(':', '', $keyFound);
                if (!$value && mb_strtolower($keyFound) === mb_strtolower($key)) {
                    $value = $this->getTextCrawler($spans->eq(1));
                }
            }
        });
        return $value === '-' ? '': $value;
    }

    private function getInfoPrice(Crawler $crawlerDetailProduct): array
    {
        $html = $crawlerDetailProduct->html();
        $priceNettoForQuantity = explode('ceny netto za ', $html)[1];
        $priceNettoForQuantity = explode('/', $priceNettoForQuantity)[0];
        if (Str::contains($priceNettoForQuantity, 'szt.')) {
            $priceNettoForUnit = 'szt.';
        } else {
            throw new DelivererAgripException('Unit is not supported');
        }
        $priceNettoForQuantity = $this->extractInteger(str_replace(' ', '', $priceNettoForQuantity));
        $checkPricePosition = $this->getTextCrawler($crawlerDetailProduct->filter('table tr')->eq(1)->filter('td')->eq(2));
        if ($checkPricePosition !== 'Podstaw.') {
            throw new DelivererAgripException('Valid check price position');
        }
        $checkInPackPosition = $this->getTextCrawler($crawlerDetailProduct->filter('table tr')->eq(0)->filter('td')->eq(4));
        if ($checkInPackPosition !== 'Opakowanie (szt)') {
            throw new DelivererAgripException('Valid check in pack position');
        }
        $priceNetto = $this->getTextCrawler($crawlerDetailProduct->filter('table tr')->eq(2)->filter('td')->eq(2));
        $priceNetto = str_replace(['&nbsp;', ' '], '', $priceNetto);
        $priceNetto = $this->extractFloat(str_replace(',', '.', $priceNetto));
        $inPack = $this->getTextCrawler($crawlerDetailProduct->filter('table tr')->eq(2)->filter('td')->eq(6));
        $inPack = str_replace(['&nbsp;', ' '], '', $inPack);
        $inPack = $this->extractInteger(str_replace(',', '.', $inPack));
        $quantity = $this->getTrExtra('Stan dostępny', $crawlerDetailProduct);
        if (!Str::contains($quantity, 'opak')) {
            throw new DelivererAgripException('Invalid quantity');
        }
        $quantity = str_replace(['&nbsp;', ' '], '', $quantity);
        $quantity = $this->extractInteger(str_replace(',', '.', $quantity));
        $priceNetto = round(($priceNetto / $priceNettoForQuantity) * $inPack, 2);
        return [
            'price_netto_for_quantity' => $priceNettoForQuantity,
            'price_nett_for_unit' => $priceNettoForUnit,
            'price_netto' => $priceNetto,
            'in_pack' => $inPack,
            'quantity' => $quantity,
        ];
    }

    private function getName(Crawler $crawlerDetailProduct, $infoPrice)
    {
        $name = $this->getTextCrawler($crawlerDetailProduct->filter('h1'));
        $name .= ' ' . $this->getTextCrawler($crawlerDetailProduct->filter('h2'));
        $name .= sprintf(' /%s%s', $infoPrice['in_pack'], $infoPrice['price_nett_for_unit']);
        return $name;
    }

}
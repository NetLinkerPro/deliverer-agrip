<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class SupremisB2bListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor;

    /** @var SupremisB2bWebsiteClient $webapiClient */
    protected $websiteClient;

    /** @var string $lastContentWebsiteClient */
    protected $lastContentWebsiteClient;

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
        $this->websiteClient = app(SupremisB2bWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
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
        $groups = $this->getGroups();
        $fromNameGroup = explode(';', $this->fromAddProduct)[0] ?? null;
        $fromSubGroup = explode(';', $this->fromAddProduct)[1] ?? null;
        foreach ($groups as $group) {
            if ($fromNameGroup === $group->getName()){
                $fromNameGroup = null;
            }
            if ($fromNameGroup){
                continue;
            }
            $subGroups = $this->getSubGroups($group);
            foreach ($subGroups as $subGroup) {
                if ($fromSubGroup === $subGroup->getName()){
                    $fromSubGroup = null;
                }
                if ($fromSubGroup){
                    continue;
                }
                DelivererLogger::log(sprintf('Sub group: %s => %s.', $group->getName(), $subGroup->getName()));
                $crawlerPage = $this->getCrawlerFirstPage($subGroup);
                $page = 1;
                while($crawlerPage){
                    DelivererLogger::log(sprintf('List products: %s => %s, page %s.', $group->getName(), $subGroup->getName(), $page));
                    $listProducts = $this->getListProduct($group, $subGroup, $crawlerPage);
                    foreach ($listProducts as $product) {
                        DelivererLogger::log(sprintf('Product: %s.', $product->getName()));
                        yield $product;
                    }
                    $crawlerPage = $this->getCrawlerNextPage($crawlerPage);
                    $page++;
                }
            }
            $this->backToGroups();
        }
    }

    /**
     * Get groups
     *
     * @return array|CategorySource[]
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getGroups(): array
    {
        $this->getContentAfterLogin();
        $dataAspxSite = $this->websiteClient->getDataAspx($this->lastContentWebsiteClient);
        $optionsClient = [
            '_' => [
                'method' => 'post',
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' => $dataAspxSite['event_target'],
                '__EVENTARGUMENT' => $dataAspxSite['event_argument'],
                '__VIEWSTATE' => $dataAspxSite['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '53',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                'ctl00$cph_top$hMinDate' => '',
                'ctl00$cph_top$Button_Sciezka_2' => 'Optymalny   -5%',
                'ctl00$hdnZwin' => 'zwiń',
                'ctl00$hdnRozwin' => 'rozwiń',
            ],
        ];
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
        $crawler = $this->getCrawler($content);
        $groups = $crawler->filter('#ctl00_cph_top_wyszuk2_Repeater1 div.ui-item-catalogue')
            ->each(function (Crawler $item) use (&$dataAspxSite) {
                $id = sprintf('1-%s', Str::slug($this->getTextCrawler($item->filter('h3'))));
                $name = $this->getTextCrawler($item->filter('h3'));
                $url = 'https://www.agrip-b2b.com.pl/Zamowienie.aspx';
                $category = new CategorySource($id, $name, $url);
                $category->setProperty('position_category_grid', $this->getPositionCategoryGrid($item));
                return $category;
            });
        if (!$groups) {
            throw new DelivererAgripException('Not found groups in B2B.');
        }
        return $groups;
    }

    /**
     * Get sub groups
     *
     * @param CategorySource $group
     * @return array|CategorySource[]
     * @throws DelivererAgripException|GuzzleException
     */
    private function getSubGroups(CategorySource $group): array
    {
        $dataAspxSite = $this->websiteClient->getDataAspx($this->lastContentWebsiteClient);
        $optionsClient = [
            '_' => [
                'method' => 'post',
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' => sprintf('ctl00$cph_top$wyszuk2$Repeater1$ctl%s$MainLinkButton', $group->getProperty('position_category_grid')),
                '__EVENTARGUMENT' => '',
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAspxSite['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '53',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                'ctl00$cph_top$hMinDate' => '',
                'ctl00$cph_top$TextBox_Indeks' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender1_ClientState' => '',
                'ctl00$cph_top$TextBox_Nazwa' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender2_ClientState' => '',
                'ctl00$cph_top$DropDownList_Grupy' => '*',
                'ctl00$cph_top$TextBox_Netto' => '0,00',
                'ctl00$cph_top$TextBox_Weight' => '0,00',
                'ctl00$cph_top$TextBox_Volume' => '0,00',
                'ctl00$ContentPlaceHolder1$DropDownList_Ilosc' => '25',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl02$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl03$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl04$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl05$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl06$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl07$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl08$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl09$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl10$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl11$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl12$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl13$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl14$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl15$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl16$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl17$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl18$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl19$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl20$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl21$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl22$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl23$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl24$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl25$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl26$textBox' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Ordering' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Waluta_CZ' => 'PLN',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Indeks' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Nazwa' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Grupa' => '*',
                'ctl00$ContentPlaceHolder1$Hidden_Grupa_Index' => '',
                'ctl00$ContentPlaceHolder1$Hidden1' => '0',
                'ctl00$ContentPlaceHolder1$Hidden2' => '',
                'ctl00$ContentPlaceHolder1$Hidden_CurrentPage' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Filter' => '',
                'ctl00$ContentPlaceHolder1$Hidden_OH_Mail' => '',
                'ctl00$hdnZwin' => 'zwiń',
                'ctl00$hdnRozwin' => 'rozwiń',
            ],
        ];
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
        $crawler = $this->getCrawler($content);
        return $crawler->filter('#ctl00_cph_top_wyszuk2_Repeater1 div.ui-item-group')
            ->each(function (Crawler $item) use (&$group) {
                $id = sprintf('2-%s', Str::slug($this->getTextCrawler($item->filter('h3'))));
                $name = $this->getTextCrawler($item->filter('h3'));
                $url = 'https://www.agrip-b2b.com.pl/Zamowienie.aspx';
                $category = new CategorySource($id, $name, $url);
                $category->setProperty('position_category_grid', $this->getPositionCategoryGrid($item));
                $group->addChild($category);
                return $category;
            });
    }

    /**
     * Get list product
     *
     * @param CategorySource $group
     * @param CategorySource $subGroup
     * @param Crawler $crawler
     * @return array| ProductSource[]
     */
    private function getListProduct(CategorySource $group, CategorySource $subGroup, Crawler $crawler): array
    {
        $listProduct = $crawler->filter('#ctl00_ContentPlaceHolder1_GridView_Katalog tr')
            ->each(function (Crawler $item) use (&$group, $subGroup) {
                $quantityTds = $item->filter('td')->count();
                if ($quantityTds < 12){
                    return null;
                }
                $id = $this->getTextCrawler($item->filter('td')->eq(0));
                $name = $this->getName($item);
                if (!$name){
                    return null;
                }
                $stock = (int) $this->getTextCrawler($item->filter('td')->eq(2));
                $unit = $this->getUnit($item);
                $price = $this->getPrice($item);
                $url = 'https://www.agrip-b2b.com.pl/Zamowienie.aspx';
                $category = $this->getCategory($group, $subGroup);
                $product = new ProductSource($id, $url);
                $product->setName($name);
                $product->setStock($stock);
                $product->setPrice($price);
                $product->setTax(23);
                $product->setProperty('unit', $unit);
                $product->setCategories([$category]);
                $product->setAvailability(1);
                $urlImage = $this->getUrlImage($item);
                $product->setProperty('image', $urlImage);
                return $product;
            });
        return array_filter($listProduct);
    }

    /**
     * Get content after login
     *
     * @return string
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getContentAfterLogin(): string
    {
        $optionsClient['_']['force_login'] = true;
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
        return $content;
    }

    /**
     * Get position category grid
     *
     * @param Crawler $item
     * @return string
     * @throws DelivererAgripException
     */
    private function getPositionCategoryGrid(Crawler $item): string
    {
        $idAttribute = $this->getAttributeCrawler($item->filter('a'), 'id');
        $id = str_replace(['ctl00_cph_top_wyszuk2_Repeater1_ctl', '_MainLinkButton'], '', $idAttribute);
        if (!$id) {
            throw new DelivererAgripException('Not found ID category group.');
        }
        return (string)$id;
    }

    /**
     * Back to groups
     */
    private function backToGroups(): void
    {
        $dataAspxSite = $this->websiteClient->getDataAspx($this->lastContentWebsiteClient);
        $optionsClient = [
            '_' => [
                'method' => 'post',
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' => 'ctl00$cph_top$wyszuk2$arrowImgBtn',
                '__EVENTARGUMENT' => '',
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAspxSite['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '53',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                'ctl00$cph_top$hMinDate' => '',
                'ctl00$cph_top$TextBox_Indeks' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender1_ClientState' => '',
                'ctl00$cph_top$TextBox_Nazwa' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender2_ClientState' => '',
                'ctl00$cph_top$DropDownList_Grupy' => '*',
                'ctl00$cph_top$TextBox_Netto' => '0,00',
                'ctl00$cph_top$TextBox_Weight' => '0,00',
                'ctl00$cph_top$TextBox_Volume' => '0,00',
                'ctl00$ContentPlaceHolder1$DropDownList_Ilosc' => '25',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl02$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl03$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl04$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl05$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl06$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl07$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl08$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl09$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl10$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl11$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl12$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl13$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl14$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl15$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl16$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl17$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl18$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl19$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl20$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl21$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl22$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl23$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl24$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl25$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl26$textBox' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Ordering' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Waluta_CZ' => 'PLN',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Indeks' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Nazwa' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Grupa' => '*',
                'ctl00$ContentPlaceHolder1$Hidden_Grupa_Index' => '',
                'ctl00$ContentPlaceHolder1$Hidden1' => '0',
                'ctl00$ContentPlaceHolder1$Hidden2' => '',
                'ctl00$ContentPlaceHolder1$Hidden_CurrentPage' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Filter' => '',
                'ctl00$ContentPlaceHolder1$Hidden_OH_Mail' => '',
                'ctl00$hdnZwin' => 'zwiń',
                'ctl00$hdnRozwin' => 'rozwiń',
            ],
        ];
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
    }

    /**
     * Get pages
     *
     * @param $subGroup
     * @return int
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getPages($subGroup): int
    {
        $crawlerFirstPage = $this->getCrawlerListProduct($subGroup, 1);
        if ($crawlerFirstPage->matches('.ui-pagination .ui-item')){
            return 1;
        }
        $eventTarget = $crawlerFirstPage->filter('.ui-pagination a')->last()->attr('id');
        $eventTarget = str_replace('_', '$', $eventTarget);
        $eventTarget = str_replace('GridView$Katalog', 'GridView_Katalog', $eventTarget);
        $dataAspxSite = $this->websiteClient->getDataAspx($this->lastContentWebsiteClient);
        $optionsClient = [
            '_' => [
                'method' => 'post',
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' => $eventTarget,
                '__EVENTARGUMENT' => '',
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAspxSite['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '53',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                'ctl00$cph_top$hMinDate' => '',
                'ctl00$cph_top$TextBox_Indeks' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender1_ClientState' => '',
                'ctl00$cph_top$TextBox_Nazwa' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender2_ClientState' => '',
                'ctl00$cph_top$DropDownList_Grupy' => '*',
                'ctl00$cph_top$TextBox_Netto' => '0,00',
                'ctl00$cph_top$TextBox_Weight' => '0,00',
                'ctl00$cph_top$TextBox_Volume' => '0,00',
                'ctl00$ContentPlaceHolder1$DropDownList_Ilosc' => '25',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl02$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl03$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl04$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl05$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl06$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl07$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl08$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl09$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl10$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl11$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl12$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl13$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl14$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl15$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl16$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl17$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl18$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl19$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl20$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl21$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl22$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl23$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl24$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl25$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl26$textBox' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Ordering' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Waluta_CZ' => 'PLN',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Indeks' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Nazwa' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Grupa' => '*',
                'ctl00$ContentPlaceHolder1$Hidden_Grupa_Index' => '',
                'ctl00$ContentPlaceHolder1$Hidden1' => '0',
                'ctl00$ContentPlaceHolder1$Hidden2' => '',
                'ctl00$ContentPlaceHolder1$Hidden_CurrentPage' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Filter' => '',
                'ctl00$ContentPlaceHolder1$Hidden_OH_Mail' => '',
                'ctl00$hdnZwin' => 'zwiń',
                'ctl00$hdnRozwin' => 'rozwiń',
            ],
        ];
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
        $crawler = $this->getCrawler($content);
        $pages = 1;
        $crawler->filter('.ui-pagination .ui-item')
            ->each(function(Crawler $item) use (&$pages){
                $textItem = $this->getTextCrawler($item);
                $numberPage = (int) $textItem;
                if ($numberPage > $pages){
                    $pages = $numberPage;
                }
            });
        return $pages;
    }

    /**
     * Get unit
     *
     * @param Crawler $item
     * @return string
     */
    private function getUnit(Crawler $item): string
    {
       $unit = $this->getTextCrawler($item->filter('td')->eq(6));
        return $unit === 'szt' ? 'szt.' : $unit ?? '';
    }

    /**
     * Get price
     *
     * @param Crawler $item
     * @return float
     */
    private function getPrice(Crawler $item): float
    {
        $textPrice = $this->getTextCrawler($item->filter('td')->eq(7));
        $textPrice = str_replace([' ', ','], ['', '.'], $textPrice);
        return $this->extractFloat($textPrice);
    }

    /**
     * Get category
     *
     * @param CategorySource $group
     * @param CategorySource $subGroup
     * @return CategorySource
     */
    private function getCategory(CategorySource $group, CategorySource $subGroup): CategorySource
    {
        $categoryGroup = clone $group;
        $categorySubGroup = clone $subGroup;
        $categoryGroup->setChildren([$categorySubGroup]);
        return $categoryGroup;
    }

    /**
     * Get crawler list product
     *
     * @param CategorySource $subGroup
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerListProduct(CategorySource $subGroup, int $page): Crawler
    {
        $eventTarget = $page === 1 ? sprintf('ctl00$cph_top$wyszuk2$Repeater1$ctl%s$MainLinkButton', $subGroup->getProperty('position_category_grid')) : sprintf('ctl00$ContentPlaceHolder1$GridView_Katalog$ctl%s$Button_%s', $subGroup->getProperty('position_category_grid'), $page);
        $dataAspxSite = $this->websiteClient->getDataAspx($this->lastContentWebsiteClient);
        $optionsClient = [
            '_' => [
                'method' => 'post',
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' =>$eventTarget,
                '__EVENTARGUMENT' => '',
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAspxSite['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '53',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                'ctl00$cph_top$hMinDate' => '',
                'ctl00$cph_top$TextBox_Indeks' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender1_ClientState' => '',
                'ctl00$cph_top$TextBox_Nazwa' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender2_ClientState' => '',
                'ctl00$cph_top$DropDownList_Grupy' => '*',
                'ctl00$cph_top$TextBox_Netto' => '0,00',
                'ctl00$cph_top$TextBox_Weight' => '0,00',
                'ctl00$cph_top$TextBox_Volume' => '0,00',
                'ctl00$ContentPlaceHolder1$DropDownList_Ilosc' => '25',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl02$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl03$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl04$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl05$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl06$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl07$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl08$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl09$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl10$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl11$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl12$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl13$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl14$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl15$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl16$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl17$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl18$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl19$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl20$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl21$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl22$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl23$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl24$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl25$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl26$textBox' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Ordering' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Waluta_CZ' => 'PLN',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Indeks' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Nazwa' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Grupa' => '*',
                'ctl00$ContentPlaceHolder1$Hidden_Grupa_Index' => '',
                'ctl00$ContentPlaceHolder1$Hidden1' => '0',
                'ctl00$ContentPlaceHolder1$Hidden2' => '',
                'ctl00$ContentPlaceHolder1$Hidden_CurrentPage' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Filter' => '',
                'ctl00$ContentPlaceHolder1$Hidden_OH_Mail' => '',
                'ctl00$hdnZwin' => 'zwiń',
                'ctl00$hdnRozwin' => 'rozwiń',
            ],
        ];
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
        return $this->getCrawler($content);
    }

    /**
     * Get crawler first page
     *
     * @param CategorySource $subGroup
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerFirstPage(CategorySource $subGroup): Crawler
    {
        $dataAspxSite = $this->websiteClient->getDataAspx($this->lastContentWebsiteClient);
        $optionsClient = [
            '_' => [
                'method' => 'post',
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' =>sprintf('ctl00$cph_top$wyszuk2$Repeater1$ctl%s$MainLinkButton', $subGroup->getProperty('position_category_grid')),
                '__EVENTARGUMENT' => '',
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAspxSite['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '53',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                'ctl00$cph_top$hMinDate' => '',
                'ctl00$cph_top$TextBox_Indeks' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender1_ClientState' => '',
                'ctl00$cph_top$TextBox_Nazwa' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender2_ClientState' => '',
                'ctl00$cph_top$DropDownList_Grupy' => '*',
                'ctl00$cph_top$TextBox_Netto' => '0,00',
                'ctl00$cph_top$TextBox_Weight' => '0,00',
                'ctl00$cph_top$TextBox_Volume' => '0,00',
                'ctl00$ContentPlaceHolder1$DropDownList_Ilosc' => '25',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl02$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl03$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl04$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl05$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl06$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl07$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl08$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl09$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl10$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl11$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl12$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl13$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl14$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl15$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl16$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl17$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl18$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl19$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl20$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl21$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl22$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl23$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl24$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl25$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl26$textBox' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Ordering' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Waluta_CZ' => 'PLN',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Indeks' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Nazwa' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Grupa' => '*',
                'ctl00$ContentPlaceHolder1$Hidden_Grupa_Index' => '',
                'ctl00$ContentPlaceHolder1$Hidden1' => '0',
                'ctl00$ContentPlaceHolder1$Hidden2' => '',
                'ctl00$ContentPlaceHolder1$Hidden_CurrentPage' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Filter' => '',
                'ctl00$ContentPlaceHolder1$Hidden_OH_Mail' => '',
                'ctl00$hdnZwin' => 'zwiń',
                'ctl00$hdnRozwin' => 'rozwiń',
            ],
        ];
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
        return $this->getCrawler($content);
    }

    /**
     * Get crawler next page
     *
     * @param Crawler $crawlerCurrent
     * @return Crawler|null
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerNextPage(Crawler $crawlerCurrent): ?Crawler
    {
        $quantityAElements = $crawlerCurrent->filter('.ui-pagination > a')->count();
        if ($quantityAElements <= 2){
            return null;
        }
        $classDivLast = $crawlerCurrent->filter('.ui-pagination > div')->last()->attr('class');
        if (Str::contains($classDivLast, 'ui-item-current')){
            return null;
        }
        $eventTarget = $crawlerCurrent->filter('.ui-pagination > a')->eq($quantityAElements -2 )->attr('id');
        $eventTarget = str_replace('_', '$', $eventTarget);
        $eventTarget = str_replace('GridView$Katalog', 'GridView_Katalog', $eventTarget);
        $dataAspxSite = $this->websiteClient->getDataAspx($this->lastContentWebsiteClient);
        $optionsClient = [
            '_' => [
                'method' => 'post',
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' =>$eventTarget,
                '__EVENTARGUMENT' => '',
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAspxSite['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                '__SCROLLPOSITIONX' => '0',
                '__SCROLLPOSITIONY' => '53',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                'ctl00$cph_top$hMinDate' => '',
                'ctl00$cph_top$TextBox_Indeks' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender1_ClientState' => '',
                'ctl00$cph_top$TextBox_Nazwa' => '',
                'ctl00$cph_top$TextBoxWatermarkExtender2_ClientState' => '',
                'ctl00$cph_top$DropDownList_Grupy' => '*',
                'ctl00$cph_top$TextBox_Netto' => '0,00',
                'ctl00$cph_top$TextBox_Weight' => '0,00',
                'ctl00$cph_top$TextBox_Volume' => '0,00',
                'ctl00$ContentPlaceHolder1$DropDownList_Ilosc' => '25',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl02$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl03$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl04$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl05$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl06$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl07$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl08$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl09$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl10$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl11$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl12$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl13$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl14$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl15$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl16$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl17$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl18$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl19$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl20$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl21$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl22$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl23$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl24$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl25$textBox' => '',
                'ctl00$ContentPlaceHolder1$GridView_Katalog$ctl26$textBox' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Ordering' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Waluta_CZ' => 'PLN',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Indeks' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Nazwa' => '',
                'ctl00$ContentPlaceHolder1$Hidden_Filter_Grupa' => '*',
                'ctl00$ContentPlaceHolder1$Hidden_Grupa_Index' => '',
                'ctl00$ContentPlaceHolder1$Hidden1' => '0',
                'ctl00$ContentPlaceHolder1$Hidden2' => '',
                'ctl00$ContentPlaceHolder1$Hidden_CurrentPage' => '1',
                'ctl00$ContentPlaceHolder1$Hidden_Filter' => '',
                'ctl00$ContentPlaceHolder1$Hidden_OH_Mail' => '',
                'ctl00$hdnZwin' => 'zwiń',
                'ctl00$hdnRozwin' => 'rozwiń',
            ],
        ];
        $content = $this->websiteClient->getContent('https://www.agrip-b2b.com.pl/Zamowienie.aspx', $optionsClient);
        $this->lastContentWebsiteClient = $content;
        return $this->getCrawler($content);
    }

    /**
     * Get name
     *
     * @param Crawler $item
     * @return string
     */
    private function getName(Crawler $item): string
    {
        $name = $this->getTextCrawler($item->filter('td')->eq(1));
        $name = preg_replace('/\s+/',  ' ',$name);
        return trim($name);
    }

    /**
     * Get URL image
     *
     * @param Crawler $item
     * @return string
     */
    private function getUrlImage(Crawler $item): string
    {
        $attributeHref = $this->getAttributeCrawler($item->filter('td')->eq(14)->filter('a'), 'href');
        $attributeHref = trim($attributeHref);
        if ($attributeHref){
            $urlImage = sprintf('https://www.agrip-b2b.com.pl/%s', $attributeHref);
        } else {
            $urlImage = '';
        }
        return $urlImage;
    }

}
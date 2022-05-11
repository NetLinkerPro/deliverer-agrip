<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MagresnetWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use Symfony\Component\DomCrawler\Crawler;

class MagresnetListCategories implements ListCategories
{
    use CrawlerHtml, LimitString;

    /** @var MagresnetWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(MagresnetWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
    }

    /**
     * Get
     *
     * @return Generator|CategorySource[]|array
     */
    public function get(): Generator
    {
//        $categories = $this->getCategories();
        $categories = $this->getMainCategories();
        foreach ($categories as $category) {
            yield $category;
        }
    }

    /**
     * Get categories
     *
     * @return array
     */
    private function getCategories(): array
    {
        $treeCategories = $this->getCacheTreeCategoriesWebsite();
        return $this->getSingleCategories($treeCategories);
    }

    /**
     * Get main categories
     *
     * @return array
     */
    private function getMainCategories(): array
    {
        return $this->getCacheMainCategoriesWebsite();
    }

    /**
     * Get main categories website
     *
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getMainCategoriesWebsite(): array
    {
        $crawler = $this->getCrawlerCategories(1);
        $pages = $this->getPages($crawler);
        $categories = [];
        $addedCategories = [];
        foreach (range(1, $pages) as $page){
            $crawler = $page === 1 ? $crawler : $this->getCrawlerCategories($page);
            $crawler->filter('#ctl00_ContentPlaceHolder1_lvGrupy_groupPlaceholderContainer tr')
                ->each(function(Crawler $tr) use (&$categories, &$addedCategories, $page){
                    $title = $this->getTextCrawler($tr->filter('span'));
                    if ($title && $title !== 'WSZYSTKO'){
                        $id = Str::slug($title);
                        $url = 'http://212.180.197.238/OfertaMobile.aspx';
                        $name = $this->getAttributeCrawler($tr->filter('input'), 'name');
                        $title = str_replace('"', '', $title);
//                        $position = explode('ImageButton', $value)[1];
//                        $row = explode('vGrupy$ctrl', $value)[1];
//                        $row = explode('$', $row)[0];
                        if (!in_array($id, $addedCategories)){
                            array_push($addedCategories, $id);
                            $category = new CategorySource($id, $title, $url);
                            $category->setProperty('name', $name);
                            $category->setProperty('page', $page);
                            array_push($categories, $category);
                        }
                    }
                });
        }
        return $categories;
    }

    /**
     * Get cache tree categories website
     *
     * @return array
     */
    private function getCacheTreeCategoriesWebsite(): array
    {
        $keyCache = 'deliverer-agrip_tree_categories_5';
        return Cache::remember($keyCache, 184000, function () {
            return $this->getTreeCategoriesWebsite();
        });
    }

    /**
     * Get tree categories website
     *
     * @param Crawler|null $crawlerElements
     * @param int $depth
     * @param CategorySource|null $parentCategory
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getTreeCategoriesWebsite(Crawler $crawlerElements = null, int $depth = 0, ?CategorySource $parentCategory = null): array
    {
        if (!$crawlerElements) {
            $crawlerElements = $this->getCrawlerCategories()->filter('#mega-menu-top_nav > li.mega-menu-item-type-taxonomy');
        }
        $categories = [];
        $crawlerElements->each(function (Crawler $element) use (&$categories, &$depth, &$parentCategory) {
            $aElement = $element->filter('a')->eq(0);
            $name = trim(str_replace('»', '', $this->getTextCrawler($aElement)));
            $name = str_replace('/', '-', $name);
            $id = sprintf('%s%s_%s', $parentCategory ? sprintf('%s_', $parentCategory->getId()) : '', Str::slug($name), $depth);
            $id = $this->limitReverse($id);
            $url = $this->getAttributeCrawler($aElement, 'href');
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
            if ($depth === 0 && $this->liHasChild($element)) {
                $nextDepth = $depth + 1;
                $e = $element->html();
                $crawlerChildElements = $element->filter('ul li.mega-menu-item-type-taxonomy');
                $childrenCategories = $this->getTreeCategoriesWebsite($crawlerChildElements, $nextDepth, $category);
                $category->setChildren($childrenCategories);
            }
        });
        return $categories;
    }

    /**
     * Li has child
     *
     * @param Crawler $liElement
     * @return bool
     */
    private function liHasChild(Crawler $liElement): bool
    {
        return $liElement->filter('ul')->count() > 0;
    }

    /**
     * Get list categories children
     *
     * @param $parentCategories
     * @param array $listCategoryDepth
     * @return array
     */
    private function getSingleCategories($parentCategories, array $listCategoryDepth = []): array
    {
        $depth = sizeof($listCategoryDepth) + 1;
        $listCategories = [];
        foreach ($parentCategories as $parentCategory) {
            $listCategoryDepth[$depth] = $parentCategory;
            if (!$parentCategory->getChildren()) {
                /** @var CategorySource|null $category */
                $category = null;
                /** @var CategorySource|null $categoryLast */
                $categoryLast = null;
                foreach ($listCategoryDepth as $categoryDepth) {
                    $categoryDepthClone = clone $categoryDepth;
                    $categoryDepthClone->setChildren([]);
                    if (!$category) {
                        $category = $categoryDepthClone;
                    } else {
                        $categoryLast->setChildren([$categoryDepthClone]);
                    }
                    $categoryLast = $categoryDepthClone;
                }
                array_push($listCategories, $category);
            } else {
                $listCategories = array_merge($listCategories, $this->getSingleCategories($parentCategory->getChildren(), $listCategoryDepth));
            }
        }
        unset($listCategoryDepth[$depth]);
        return $listCategories;
    }

    /**
     * Get crawler categories
     *
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerCategories(int $page): Crawler
    {
        $dataAjax = $this->websiteClient->getLastDataAspx();
        $contents = $this->websiteClient->getContentAjax('http://212.180.197.238/OfertaMobile.aspx', [
            RequestOptions::FORM_PARAMS => [
                'ctl00$ToolkitScriptManager1' => sprintf('ctl00$ContentPlaceHolder1$UpdatePanel1|ctl00$ContentPlaceHolder1$lvGrupy$DataPager1$ctl00$ctl%s%s', $page -1 < 10 ? '0' : '',$page - 1),
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
                'ctl00$ContentPlaceHolder1$hfAsortyment' => '28',
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
                '__VIEWSTATE' =>$dataAjax['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAjax['view_state_generator'],
                '__PREVIOUSPAGE' => $dataAjax['previous_page'],
                '__EVENTVALIDATION' => $dataAjax['event_validation'],
                '__VIEWSTATEENCRYPTED' => $dataAjax['view_state_encrypted'],
                '__ASYNCPOST' =>$dataAjax['async_post'],
                sprintf('ctl00$ContentPlaceHolder1$lvGrupy$DataPager1$ctl00$ctl%s%s', $page -1 < 10 ? '0' : '',$page-1) => $page,
            ]
        ]);
        return $this->getCrawler($contents);
    }

    /**
     * Get cache main categories website
     *
     * @return array
     */
    private function getCacheMainCategoriesWebsite(): array
    {
        $keyCache = 'deliverer-agrip_main_categories_6';
        return Cache::remember($keyCache, 3600, function () {
            return $this->getMainCategoriesWebsite();
        });
    }

    /**
     * Get ID category
     *
     * @param Crawler $aElement
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdCategory(Crawler $aElement): string
    {
        $href = $this->getAttributeCrawler($aElement, 'href');
        $explodeHref = explode(',', $href);
        $idCategory = $explodeHref[sizeof($explodeHref) - 1];
        if (!$idCategory) {
            throw new DelivererAgripException('Not found ID category.');
        }
        return $idCategory;
    }

    /**
     * Add product category
     *
     * @param CategorySource $category
     * @param int $depth
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function addProductCategory(CategorySource $category, int $depth): void
    {
        $contents = $this->websiteClient->getContents($category->getUrl());
        $crawler = $this->getCrawler($contents);
        $crawler->filter('ul.products-grid > li')->each(function (Crawler $liElement) use (&$category, &$depth) {
            $aElement = $liElement->filter('h2.product-name a')->eq(0);
            $name = $this->getTextCrawler($aElement);
            $url = $this->getAttributeCrawler($aElement, 'href');
            $id = sprintf('%s_%s', Str::limit(Str::slug($name), 62, ''), $depth);
            $childCategory = new CategorySource($id, $name, $url);
            $category->addChild($childCategory);
        });

    }

    /**
     * Get pages
     *
     * @param Crawler $crawler
     * @return int
     */
    private function getPages(Crawler $crawler): int
    {
        $pages = 1;
        $crawler->filter('#ctl00_ContentPlaceHolder1_lvGrupy_DataPager1 input.numeric_button')
            ->each(function(Crawler $input) use (&$pages){
               $text = $this->getAttributeCrawler($input, 'value');
               $number = (int) $text;
               if ($number > $pages){
                   $pages = $number;
               }
            });
        return $pages;
    }
}
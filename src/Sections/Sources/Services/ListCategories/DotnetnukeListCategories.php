<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\DotnetnukeWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MagresnetWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use Symfony\Component\DomCrawler\Crawler;

class DotnetnukeListCategories implements ListCategories
{
    use CrawlerHtml, LimitString;

    /** @var DotnetnukeWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
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
    }

    /**
     * Get
     *
     * @return Generator|CategorySource[]|array
     */
    public function get(): Generator
    {
        $categories = $this->getCategories();
//        $categories = $this->getMainCategories();
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
        foreach (range(1, $pages) as $page) {
            $crawler = $page === 1 ? $crawler : $this->getCrawlerCategories($page);
            $crawler->filter('#ctl00_ContentPlaceHolder1_lvGrupy_groupPlaceholderContainer tr')
                ->each(function (Crawler $tr) use (&$categories, &$addedCategories, $page) {
                    $title = $this->getTextCrawler($tr->filter('span'));
                    if ($title && $title !== 'WSZYSTKO') {
                        $id = Str::slug($title);
                        $url = 'http://212.180.197.238/OfertaMobile.aspx';
                        $name = $this->getAttributeCrawler($tr->filter('input'), 'name');
                        $title = str_replace('"', '', $title);
//                        $position = explode('ImageButton', $value)[1];
//                        $row = explode('vGrupy$ctrl', $value)[1];
//                        $row = explode('$', $row)[0];
                        if (!in_array($id, $addedCategories)) {
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
        $keyCache = 'deliverer-agrip_tree_categories_3';
        return Cache::remember($keyCache, 1184000, function () {
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
    private function getTreeCategoriesWebsite(Crawler $crawlerElements = null, ?CategorySource $parentCategory = null): array
    {
        if (!$crawlerElements) {
            $crawlerElements = $this->getCrawlerCategories()->filter('li');
        }
        $categories = [];
        $crawlerElements->each(function (Crawler $element) use (&$categories, &$depth, &$parentCategory) {
            $aElement = $element->filter('a')->eq(0);
            $name = trim($this->getTextCrawler($aElement));
            $name = str_replace('/', '-', $name);
            $id = $this->getAttributeCrawler($aElement, 'data');
            $url = 'https://www.argip.com.pl/Produkty/Zakupy.aspx';
            if ($id && $name){
                $category = new CategorySource($id, $name, $url);
                array_push($categories, $category);
                $crawlerChild = $this->getCrawlerCategories($id);
                if ($this->liHasChild($crawlerChild)) {
                    $crawlerChildElements = $crawlerChild->filter('li');
                    $childrenCategories = $this->getTreeCategoriesWebsite($crawlerChildElements, $category);
                    $category->setChildren($childrenCategories);
                }
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
        return $liElement->filter('li')->count() > 0;
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
    private function getCrawlerCategories(int $parentId = 0): Crawler
    {
        DelivererLogger::log(sprintf('Get category %s', $parentId));
        $contents = $this->websiteClient->getContentAjax('https://www.argip.com.pl/Produkty/Zakupy.aspx', [
            RequestOptions::FORM_PARAMS => [
                'ctx' => '6',
                '__DNNCAPISCI' => 'dnn$ctr418$ArgipTree',
                '__DNNCAPISCP' => '%7B%22method%22%3A%22GetWezly%22%2C%22args%22%3A%7B%22parentid%22%3A' . $parentId.'%7D%7D',
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
            ->each(function (Crawler $input) use (&$pages) {
                $text = $this->getAttributeCrawler($input, 'value');
                $number = (int)$text;
                if ($number > $pages) {
                    $pages = $number;
                }
            });
        return $pages;
    }
}
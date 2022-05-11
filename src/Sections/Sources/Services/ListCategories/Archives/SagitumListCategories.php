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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SagitumWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use Symfony\Component\DomCrawler\Crawler;

class SagitumListCategories implements ListCategories
{
    use CrawlerHtml, LimitString;

    /** @var SagitumWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(SagitumWebsiteClient::class, [
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
        $crawler = $this->getCrawlerCategories();
        $html = $crawler->html();
        $categories = [];
        $crawler->filter('ul.dropdown-menu a')->each(function (Crawler $aElement) use (&$categories) {
            $name = $this->getTextCrawler($aElement);
            $href = $this->getAttributeCrawler($aElement, 'href');
            $id = explode('?GroupID=', $href)[1];
            $url = sprintf('https://b2b.agrip.pl/Forms/%s', $href);
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
        });
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
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerCategories(): Crawler
    {
        $content = $this->websiteClient->getContents('https://b2b.agrip.pl/Forms/Articles.aspx?GroupID=75');
        return $this->getCrawler($content);
    }

    /**
     * Get cache main categories website
     *
     * @return array
     */
    private function getCacheMainCategoriesWebsite(): array
    {
        $keyCache = 'deliverer-agrip_main_categories';
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
}
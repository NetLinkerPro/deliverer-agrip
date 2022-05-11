<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\LaravelWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class LaravelListCategories implements ListCategories
{
    use CrawlerHtml, LimitString, ResourceRemember;

    /** @var LaravelWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     * @param string $login2
     */
    public function __construct(string $login, string $password, string $login2)
    {
        $this->websiteClient = app(LaravelWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
            'login2' =>$login2,
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
//        $mainCategories = $this->getMainCategories();
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
        return  $this->getCacheMainCategoriesWebsite();
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
        $categories = [];
        $crawler->filter('#mega > li > a')->each(function (Crawler $aElement) use (&$categories) {
            $name = $this->getTextCrawler($aElement);
            $id = $this->getIdCategory($aElement);
            $href = $this->getAttributeCrawler($aElement, 'href');
            $url = sprintf('https://agrip.pl/%s', $href);
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
        $keyCache = 'deliverer-agrip_tree_categories';
        $path = __DIR__ . '/../../../../../resources/data/categories.json';
        return $this->resourceRemember($path, 172000, function(){
            return $this->getTreeCategoriesWebsite();
        });
//        return Cache::remember($keyCache, 172000, function(){
//            return $this->getTreeCategoriesWebsite();
//        });
    }

    /**
     * Get tree categories website
     *
     * @param Crawler|null $crawlerLiElements
     * @param int $depth
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getTreeCategoriesWebsite(Crawler $crawlerLiElements = null, int $depth = 0): array
    {
        if (!$crawlerLiElements){
            $crawlerLiElements = $this->getCrawlerCategories()->filter('.group-menu-partial-view > ul.sidebar-menu > li');
        }
        $categories = [];
        $crawlerLiElements->each(function (Crawler $liElement) use (&$categories, &$depth) {
            $aElement = $liElement->filterXPath('child::li/a');
            if ($depth === 0){
                echo "";
            }
            if ($aElement->count()){
                $name = $this->getTextCrawler($aElement);
                if (Str::contains($name, '.')){
                    $newName =trim(explode('.', $name)[1]);
                    $name = $newName ? $newName : $name;
                }
                $id = $this->getIdCategory($aElement);
                $url = sprintf('https://b2b.agrip.pl/group/%s', $id);
                $category = new CategorySource($id, $name, $url);
                array_push($categories, $category);
                $liChildrenElements = $this->getCrawlerCategories($id)->filter('ul.menu-open')->eq($depth)->filter('li');
                $newDepth = $depth +1;
                $childrenCategories = $this->getTreeCategoriesWebsite($liChildrenElements, $newDepth);
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
        foreach ($parentCategories as $parentCategory){
            $listCategoryDepth[$depth] = $parentCategory;
            if (!$parentCategory->getChildren()){
                /** @var CategorySource|null $category */
                $category = null;
                /** @var CategorySource|null $categoryLast */
                $categoryLast = null;
                foreach ($listCategoryDepth as $categoryDepth){
                    $categoryDepthClone = clone $categoryDepth;
                    $categoryDepthClone->setChildren([]);
                    if (!$category){
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
     * @param string|null $categoryId
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getCrawlerCategories(?string $categoryId = null): Crawler
    {
        if(!$categoryId){
            $url = 'https://b2b.agrip.pl/';
        } else {
            $url = sprintf('https://b2b.agrip.pl/group/%s', $categoryId);
        }
        $content = $this->websiteClient->getContents($url);
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
        return Cache::remember($keyCache, 3600, function(){
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
        $id = explode('group/', $href)[1];
        $id = explode('/', $id)[0];
        if (!$id){
            throw new DelivererAgripException('Invalid ID category.');
        }
        return $id;
    }
}
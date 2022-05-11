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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\CorpsWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SymfonyWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class CorpsListCategories implements ListCategories
{
    use CrawlerHtml;

    /** @var CorpsWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(CorpsWebsiteClient::class, [
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
     * Get tree categories website
     *
     * @param Crawler|null $crawlerLiElements
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getTreeCategoriesWebsite(Crawler $crawlerLiElements = null): array
    {
        if (!$crawlerLiElements){
            $crawlerLiElements = $this->getCrawlerCategories()->filter('#grupy-produktow > li');
        }
        $categories = [];
        $crawlerLiElements->each(function (Crawler $liElement) use (&$categories) {
            $name = $this->getTextCrawler($liElement->filter('a'));
            $name = str_replace(' /', ',', $name);
            $id = $this->getIdCategory($liElement);
            $url = sprintf('https://b2b.agrip.pl/produkty-do-kategorii/%s', $id);
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
            if ($this->liHasChild($liElement)){
                $liChildrenElements = $liElement->filterXPath('child::li/ul/li');
                $childrenCategories = $this->getTreeCategoriesWebsite($liChildrenElements);
                $category->setChildren($childrenCategories);
            }
        });
        return $categories;
    }

//    /**
//     * Get tree categories website
//     *
//     * @param string $idParent
//     * @return array
//     * @throws DelivererAgripException
//     * @throws GuzzleException
//     */
//    private function getTreeCategoriesWebsite(string $idParent = '633'): array
//    {
//        $crawler = $this->getCrawlerAjaxCategory($idParent);
//        $categories = [];
//        $crawler->filter('body > li')->each(function (Crawler $li) use (&$categories, &$categoryParent) {
//            $name = $this->getAttributeCrawler($li, 'data-urlname');
//            $id = $this->getAttributeCrawler($li, 'data-id');
//            $url = sprintf('https://b2b.agrip.net.pl/Towary/%s/%s', $name, $id);
//            $category = new CategorySource($id, $name, $url);
//            array_push($categories, $category);
//            if ($this->liHasChild($li)){
//                $category->setChildren($this->getTreeCategoriesWebsite($id));
//            }
//        });
//        return $categories;
//    }

    /**
     * Get crawler categories
     *
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getCrawlerCategories(): Crawler
    {
        $content = $this->websiteClient->getContent('https://b2b.agrip.pl');
        return $this->getCrawler($content);
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
     * Get cache tree categories website
     *
     * @return array
     */
    private function getCacheTreeCategoriesWebsite(): array
    {
        $keyCache = 'deliverer-agrip_tree_categories';
        return Cache::remember($keyCache, 3600, function(){
            return $this->getTreeCategoriesWebsite();
        });
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
     * Get ID category
     *
     * @param Crawler $liElement
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdCategory(Crawler $liElement): string
    {
        $href = $this->getAttributeCrawler($liElement->filter('a'), 'data-target');
        $explodeHref = explode('#drzewo-produktow-', $href);
        $idCategory = $explodeHref[1] ??'';
        if (!$idCategory){
            throw new DelivererAgripException('Not found ID category.');
        }
        return $idCategory;
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WmcWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\LimitString;
use Symfony\Component\DomCrawler\Crawler;

class WmcListCategories implements ListCategories
{
    use CrawlerHtml, LimitString;

    /** @var WmcWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(WmcWebsiteClient::class, [
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
        $keyCache = 'deliverer-agrip_tree_categories_2';
        return Cache::remember($keyCache, 3600, function(){
            return $this->getTreeCategoriesWebsite();
        });
    }

    /**
     * Get tree categories website
     *
     * @param Crawler|null $crawlerLiElements
     * @param int $depth
     * @param string $idParent
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getTreeCategoriesWebsite(Crawler $crawlerLiElements = null, int $depth = 0, string $idParent = ''): array
    {
        if (!$crawlerLiElements){
            $crawlerLiElements = $this->getCrawlerCategories()->filter('ul.menu_level_0 > li');
        }
        $categories = [];
        $crawlerLiElements->each(function (Crawler $liElement) use (&$categories, &$depth, &$idParent) {
            $aElement = $liElement->filterXPath('child::li/a');
            $name = $this->getTextCrawler($aElement);
            if (!in_array($name, ['Pokaż wszystkie produkty'])){
                $id = $this->getIdCategory($aElement);
                if ($idParent){
                    $id = sprintf('%s_%s_%s', $idParent, $depth, $id);
                } else {
                    $id = sprintf('%s_%s', $depth, $id);
                }
                $id = $this->limitReverse($id);
                $url = 'https://b2b.agrip.pl/wmc/order/order/list-product';
                $category = new CategorySource($id, $name, $url);
                array_push($categories, $category);
                if ($this->liHasChild($liElement)){
                    $liChildrenElements = $liElement->filterXPath('child::li/ul/li');
                    $newDepth = $depth +1;
                    $childrenCategories = $this->getTreeCategoriesWebsite($liChildrenElements, $newDepth, $id);
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
     * @return Crawler
     * @throws DelivererAgripException
     */
    private function getCrawlerCategories(): Crawler
    {
        $content = $this->websiteClient->getContents('https://b2b.agrip.pl/wmc/order/order/list-product');
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
        if (!Str::contains($href, 'category=')){
            $name = $this->getTextCrawler($aElement->filter('span'));
            return Str::slug($name);
        }
        $id = explode('category=', $href)[1];
        if (!$id){
            throw new DelivererAgripException('Invalid ID category.');
        }
        return sprintf('_%s', $id);
    }
}
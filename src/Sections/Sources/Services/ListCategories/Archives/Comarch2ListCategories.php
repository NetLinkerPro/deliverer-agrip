<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Comarch2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\ComarchWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class Comarch2ListCategories implements ListCategories
{
    use CrawlerHtml;

    /** @var Comarch2WebsiteClient $webapiClient */
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
        $this->websiteClient = app(Comarch2WebsiteClient::class, [
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
        return Cache::remember($keyCache, 3600, function(){
            return $this->getTreeCategoriesWebsite();
        });
    }

    /**
     * Get tree categories website
     *
     * @param string|null $idTree
     * @param string|null $idParent
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getTreeCategoriesWebsite(?string $idTree = '0'): array
    {
        $url = sprintf('http://www.b2b.agrip.info/api/items/tree/%s?parentId=null', $idTree);
        $contents = $this->websiteClient->getContentAjax($url, [], 'GET', '{"set2":[');
        $jsonData = json_decode($contents,  true, 512, JSON_UNESCAPED_UNICODE);
        $jsonCategories = $jsonData['set1'] ?? [];
        $categories = [];
        foreach ($jsonCategories as $jsonCategory){
            $name = $jsonCategory['name'];
            if (Str::contains(mb_strtolower($name), 'produkty rekomendowane') || !$name){
                continue;
            }
            $id = $jsonCategory['id'];
            $url = sprintf('http://www.b2b.agrip.info/items/%s?parent=null', $id);
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
            $isExpand = $jsonCategory['isExpand'];
            if ($isExpand){
                $childrenCategories = $this->getTreeCategoriesWebsite($id);
                $category->setChildren($childrenCategories);
            }
        }
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
        $content = $this->websiteClient->getContent('https://agrip.pl');
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
        $explodeHref = explode(',', $href);
        $idCategory = $explodeHref[sizeof($explodeHref)-1];
        if (!$idCategory){
            throw new DelivererAgripException('Not found ID category.');
        }
        return $idCategory;
    }
}
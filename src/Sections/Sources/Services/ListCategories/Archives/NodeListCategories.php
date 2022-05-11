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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\IdosellWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NodeWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class NodeListCategories implements ListCategories
{
    use CrawlerHtml;

    /** @var NodeWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(NodeWebsiteClient::class, [
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
        $categories = $this->getLastCategories();
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
     * Get last categories
     *
     * @return array
     */
    private function getLastCategories(): array
    {
        return $this->getCacheLastCategoriesWebsite();
    }

    /**
     * Get main categories website
     *
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getLastCategoriesWebsite(): array
    {
        $dataCategories = $this->getDataCategories();
        $categories = [];
        foreach ($dataCategories as $key => $node){
            if (in_array($key, ['21872', '30783', '30947'])){
                continue;
            }
            if ($node[4] !== '1'){
                continue;
            }
            $id = $node[5];
            $name = $node[2];
            $url = sprintf('https://www.agrip.pl/offer/pl/_/#/list/?gr=%s', $id);
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
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
        $keyCache = 'deliverer-agrip_tree_categories';
        return Cache::remember($keyCache, 3600, function () {
            return $this->getTreeCategoriesWebsite();
        });
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
        if (!$crawlerLiElements) {
            $crawlerLiElements = $this->getDataCategories()->filter('#mega > li');
        }
        $categories = [];
        $crawlerLiElements->each(function (Crawler $liElement) use (&$categories) {
            $aElement = $liElement->filterXPath('child::li/a');
            $name = $this->getTextCrawler($aElement);
            $id = $this->getIdCategory($aElement);
            $href = $this->getAttributeCrawler($aElement, 'href');
            $url = sprintf('https://agrip.pl/%s', $href);
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
            if ($this->liHasChild($liElement)) {
                $liChildrenElements = $liElement->filterXPath('child::li/ul/li');
                $childrenCategories = $this->getTreeCategoriesWebsite($liChildrenElements);
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
     * Get data categories
     *
     * @return array
     * @throws DelivererAgripException
     */
    private function getDataCategories(): array
    {
        $contents = $this->websiteClient->getContentAjax('https://www.agrip.pl/ajax.php', [
            RequestOptions::FORM_PARAMS => [
                'ajax_cat_mode' => 'normal',
                'ajax_cat_type' => 'categories',
                'ajax_lng' => 'pl',
                'req' => 'CatMenu',
                'url' => 'https://www.agrip.pl/',
                'locale_ajax_lang' => 'pl',
            ]
        ]);
        $data = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
        $dataParts = $data[1];
        foreach ($dataParts as $dataPart){
            if ($dataPart['type'] === 'nodes'){
                return $dataPart['data'];
            }
        }
        throw new DelivererAgripException('Not found nodes of categories.');
    }

    /**
     * Get cache last categories website
     *
     * @return array
     */
    private function getCacheLastCategoriesWebsite(): array
    {
        $keyCache = 'deliverer-agrip_last_categories';
        return Cache::remember($keyCache, 3600, function () {
            return $this->getLastCategoriesWebsite();
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
        $explodeHref = explode('-', $href);
        $hrefPart = $explodeHref[sizeof($explodeHref) - 1];
        $idCategory = str_replace('.html', '', $hrefPart);
        if (!$idCategory) {
            throw new DelivererAgripException('Not found ID category.');
        }
        return $idCategory;
    }
}
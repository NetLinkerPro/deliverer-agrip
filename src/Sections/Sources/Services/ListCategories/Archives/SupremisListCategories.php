<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AspWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class SupremisListCategories implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml;

    /** @var SupremisWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     */
    public function __construct()
    {
        $this->websiteClient = app(SupremisWebsiteClient::class);
    }

    /**
     * Get
     *
     * @return Generator|CategorySource[]|array
     */
    public function get(): Generator
    {
        $categories = $this->getCategoriesResourceRemember();
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
        $groups = $this->getGroups();
        foreach ($groups as $group){
            $subGroups = $this->getSubGroups($group);
            foreach ($subGroups as $subGroup){
                $this->getProductGroups($subGroup);
            }
        }
        return $this->getSingleCategories($groups);
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
     * Get groups
     *
     * @return array|CategorySource[]
     */
    private function getGroups(): array
    {
        $content = $this->websiteClient->getContentAnonymous('https://www.agrip.com.pl/index/produkty-pl');
        $crawler = $this->getCrawler($content);
        return $crawler->filter('div.ui-item-catalogue')->each(function (Crawler $item) {
            $id = $this->getAttributeCrawler($item, 'id');
            $name = $this->getTextCrawler($item->filter('h2'));
            $url = sprintf('%s%s', 'https://www.agrip.com.pl/', $this->getAttributeCrawler($item->filter('a.ui-products'), 'href'));
            return new CategorySource($id, $name, $url);
        });
    }

    /**
     * Get sub groups
     *
     * @param CategorySource $group
     * @return array|CategorySource[]
     */
    private function getSubGroups(CategorySource $group): array
    {
        $content = $this->websiteClient->getContentAnonymous($group->getUrl());
        $crawler = $this->getCrawler($content);
        return $crawler->filter('table.ui-table-prod-group tr.ui-odd')
            ->each(function (Crawler $item) use (&$group) {
            $id = $this->getTextCrawler($item->filter('h2'));
            $name = $this->getTextCrawler($item->filter('h2'));
            $url = sprintf('%s%s', 'https://www.agrip.com.pl/', $this->getAttributeCrawler($item->filter('a'), 'href'));
            $category =  new CategorySource($id, $name, $url);
            $group->addChild($category);
            return $category;
        });
    }

    /**
     * Get product groups
     *
     * @param CategorySource $subGroup
     * @return array| CategorySource[]
     */
    private function getProductGroups(CategorySource $subGroup): array
    {
        $content = $this->websiteClient->getContentAnonymous($subGroup->getUrl());
        $crawler = $this->getCrawler($content);
        return $crawler->filter('table.ui-table-products .ui-table-body tr')
            ->each(function (Crawler $item) use (&$subGroup) {
                $id = $this->getTextCrawler($item->filter('h2'));
                $name = $this->getTextCrawler($item->filter('h2'));
                $url = sprintf('%s%s', 'https://www.agrip.com.pl/', $this->getAttributeCrawler($item->filter('a'), 'href'));
                $category =  new CategorySource($id, $name, $url);
                $description = $this->getDescription($item);
                $image = $this->getImage($item);
                $codeProductGroup = $this->getTextCrawler($item->filter('td.ui-col-code'));
                $category->setProperty('description', $description);
                $category->setProperty('image', $image);
                $category->setProperty('code_product_group', $codeProductGroup);
                $subGroup->addChild($category);
                return $category;
            });
    }

    /**
     * Get description
     *
     * @param Crawler $item
     * @return string
     */
    private function getDescription(Crawler $item): string
    {
        $html = $item->filter('td.ui-col-name')->html();
        $html = $this->cleanAttributesHtml($html);
        $html = $this->cleanEmptyTagsHtml($html);
        return str_replace(['<h2>', '</h2>','<a>', '</a>'], ['<h4>', '</h4>', '<span>', '</span>'], $html);
    }

    /**
     * Get categories remember resource
     *
     * @return array
     */
    private function getCategoriesResourceRemember(): array
    {
        $pathResource = __DIR__ . '/../../../../../resources/data/categories.data';
        return $this->resourceRemember($pathResource, 604800,  function (){
            return $this->getCategories();
        });
    }

    /**
     * Get image
     *
     * @param Crawler $item
     * @return string
     */
    private function getImage(Crawler $item): string
    {
        $attributeSrc = $this->getAttributeCrawler($item->filter('img'), 'src');
        return sprintf('%s%s', 'https://www.agrip.com.pl', $attributeSrc);
    }
}
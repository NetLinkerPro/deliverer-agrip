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
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class AspListCategories implements ListCategories
{
    use CrawlerHtml;

    /** @var AspWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(AspWebsiteClient::class, [
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
     * Get body XML category list
     *
     * @return string
     */
    private function getBodyXmlCategoryList(): string
    {
        $sessionKey = $this->webapiClient->getSessionKey();
        return sprintf('<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <getCategoryList xmlns="http://webapi.agrip.pl/">
                  <SessionKey>%s</SessionKey>
                  <Language>pl-PL</Language>
                </getCategoryList>
              </soap:Body>
            </soap:Envelope>', $sessionKey);
    }

    /**
     * Get content XML response
     *
     * @param string $body
     * @return string
     */
    private function getContentXmlResponse(string $body): string
    {
        $keyCache = 'deliverer-agrip_list_categories';
        return Cache::remember($keyCache, 3600, function () use (&$body) {
            return $this->webapiClient->request($body);
        });
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
     * @param string $idParent
     * @return array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getTreeCategoriesWebsite(string $idParent = '633'): array
    {
        $crawler = $this->getCrawlerAjaxCategory($idParent);
        $categories = [];
        $crawler->filter('body > li')->each(function (Crawler $li) use (&$categories, &$categoryParent) {
            $name = $this->getAttributeCrawler($li, 'data-urlname');
            $id = $this->getAttributeCrawler($li, 'data-id');
            $url = sprintf('https://b2b.agrip.net.pl/Towary/%s/%s', $name, $id);
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
            if ($this->liHasChild($li)){
                $category->setChildren($this->getTreeCategoriesWebsite($id));
            }
        });
        return $categories;
    }

    /**
     * Get crawler AJAX content
     *
     * @param string $idParent
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerAjaxCategory(string $idParent): Crawler
    {
        $options = [
            RequestOptions::FORM_PARAMS => [
                'parentId' => $idParent,
            ],
            'headers' => [
                'x-requested-with' => 'XMLHttpRequest',
            ]
        ];
        $content = $this->websiteClient->getContentAjax('https://b2b.agrip.net.pl/Category/GetCategories', $options);
        $data = json_decode($content, true);
        $html = $data['Data']['html'];
        return $this->getCrawler($html);
    }

    /**
     * Li has child
     *
     * @param Crawler $li
     * @return bool
     */
    private function liHasChild(Crawler $li): bool
    {
        return Str::contains($this->getAttributeCrawler($li, 'class'), 'has-child');
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
}
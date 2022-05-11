<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives;


use Generator;
use Illuminate\Support\Facades\Cache;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Magento2WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class Magento2ListCategories implements ListCategories
{
    const KEY_CACHE = 'agrip_magento_2_list_categories';

    use CrawlerHtml;

    /** @var WebsiteClient $websiteClient */
    private $websiteClient;

    /**
     * Magento2ListCategories constructor
     */
    public function __construct()
    {
        $this->websiteClient = app(Magento2WebsiteClient::class);
    }

    /**
     * Get
     *
     * @return Generator|CategorySource[]
     * @throws DelivererAgripException
     */
    public function get(): Generator
    {
        $contentCategoryAnonymous = $this->getContentCategoryAnonymousCache();
        $mainCategories = $this->getMainCategories($contentCategoryAnonymous);
        foreach ($mainCategories as $mainCategory){
            yield $mainCategory;
        }
    }

    /**
     * Get content category
     *
     * @return string
     */
    private function getContentCategoryAnonymous(): string
    {
        $url = 'https://agrip.de';
        return $this->websiteClient->getContentAnonymous($url);
    }

    /**
     * Get content category cache
     *
     * @return string
     */
    private function getContentCategoryAnonymousCache(): string
    {
        return Cache::remember(self::KEY_CACHE, 17200, function(){
            return $this->getContentCategoryAnonymous();
        });
    }

    /**
     * Get main categories
     *
     * @param string $contentCategoryAnonymous
     * @return CategorySource[]
     * @throws DelivererAgripException
     */
    private function getMainCategories(string $contentCategoryAnonymous): array
    {
       $mainCategories = [];
       $crawler = $this->getCrawler($contentCategoryAnonymous);
       $crawler->filter('nav.cs-navigation > ul > li.cs-navigation__item--with-flyout')
           ->each(function(Crawler $li) use (&$mainCategories){
               $id = $this->extractIdProduct($li);
               $name = $this->getTextCrawler($li->filterXPath('child::*/a[1]'));
               $url = sprintf('https://agrip.de/catalog/category/view/id/%s', $id);
               $categorySource = new CategorySource($id, $name, $url);
               array_push($mainCategories, $categorySource);
           });
       return $mainCategories;
    }

    /**
     * Extract ID product
     *
     * @param Crawler $li
     * @return string
     * @throws DelivererAgripException
     */
    private function extractIdProduct(Crawler $li): string
    {
        $idProduct = $this->getAttributeCrawler($li, 'data-category-id');
        if (!$idProduct){
            throw new DelivererAgripException('Not found ID product');
        }
        return $idProduct;
    }
}
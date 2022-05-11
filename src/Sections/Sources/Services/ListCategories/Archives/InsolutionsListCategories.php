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
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\InsolutionsWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use Symfony\Component\DomCrawler\Crawler;

class InsolutionsListCategories implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml;

    /** @var InsolutionsWebsiteClient $webapiClient */
    protected $websiteClient;

    /**
     * AspWebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(InsolutionsWebsiteClient::class, [
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
        $content = $this->websiteClient->getContentAnonymous('https://www.agrip.pl');
        $crawler = $this->getCrawler($content);
        return $crawler->filter('ul.deep-1 > li > a')
            ->each(function (Crawler $aHtmlElement) {
                $name = $this->getTextCrawler($aHtmlElement);
                $id = $name;
                $url = sprintf('https://www.agrip.pl%s', $this->getAttributeCrawler($aHtmlElement, 'href'));
                return new CategorySource($id, $name, $url);
            });
    }

    /**
     * Get categories remember resource
     *
     * @return array
     */
    private function getCategoriesResourceRemember(): array
    {
        $pathResource = __DIR__ . '/../../../../../resources/data/categories.data';
        return $this->resourceRemember($pathResource, 604800, function () {
            return $this->getCategories();
        });
    }
}
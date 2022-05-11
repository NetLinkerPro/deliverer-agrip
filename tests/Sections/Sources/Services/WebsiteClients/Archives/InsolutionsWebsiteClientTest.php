<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\InsolutionsWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class InsolutionsWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var InsolutionsWebsiteClient $websiteClient */
        $websiteClient = app(InsolutionsWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.pl');
        $this->assertStringContainsString('js--loginAndRegisterPanel-toggler', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        /** @var InsolutionsWebsiteClient $websiteClient */
        $websiteClient = app(InsolutionsWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContent('https://agrip.pl');
        $this->assertStringContainsString('js--user-menu-toggler', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        /** @var InsolutionsWebsiteClient $websiteClient */
        $websiteClient = app(InsolutionsWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://www.agrip.pl/pl/list/rtv?page=3&query=&sort=symbolAsc&availability=all');
        $this->assertStringContainsString('"html":"', $content);
    }
}

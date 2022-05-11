<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Ekspert2WebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Ekspert2WebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var Ekspert2WebsiteClient $websiteClient */
        $websiteClient = app(Ekspert2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.ama-europe.pl/start');
        $this->assertStringContainsString('<input type="text" name="user"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var Ekspert2WebsiteClient $websiteClient */
        $websiteClient = app(Ekspert2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://b2b.ama-europe.pl/start');
        $content = $websiteClient->getContents('https://b2b.ama-europe.pl/start');
        $this->assertStringContainsString('<a id="menu-accountButton" href="/account/logout"', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var Ekspert2WebsiteClient $websiteClient */
        $websiteClient = app(Ekspert2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://b2b.ama-europe.pl/menuitem/categories-tree/category/10381?id=%23', [
            RequestOptions::FORM_PARAMS => []
        ], 'GET', '<ul><li id="k"><');
    }
}

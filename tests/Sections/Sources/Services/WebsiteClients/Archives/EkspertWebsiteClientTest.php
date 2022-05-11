<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\EkspertWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class EkspertWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var EkspertWebsiteClient $websiteClient */
        $websiteClient = app(EkspertWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl/start');
        $this->assertStringContainsString('<input type="text" name="user"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var EkspertWebsiteClient $websiteClient */
        $websiteClient = app(EkspertWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://b2b.agrip.pl/start');
        $content = $websiteClient->getContents('https://b2b.agrip.pl/start');
        $this->assertStringContainsString('<a id="menu-accountButton" href="/account/logout"', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var EkspertWebsiteClient $websiteClient */
        $websiteClient = app(EkspertWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://b2b.agrip.pl', [
            RequestOptions::FORM_PARAMS => []
        ]);
    }
}

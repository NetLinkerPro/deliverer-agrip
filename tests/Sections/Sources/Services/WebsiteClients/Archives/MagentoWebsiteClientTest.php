<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MagentoWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MagentoWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var MagentoWebsiteClient $websiteClient */
        $websiteClient = app(MagentoWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://www.simple24.pl');
        $this->assertStringContainsString('title="Zaloguj się">Zaloguj się</a>', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var MagentoWebsiteClient $websiteClient */
        $websiteClient = app(MagentoWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://www.simple24.pl');
        $content = $websiteClient->getContents('https://www.simple24.pl');
        $this->assertStringContainsString('<span class="contact-title">Moje konto</span>', $content);
        $this->assertStringNotContainsString('title="Zaloguj się">Zaloguj się</a>', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var MagentoWebsiteClient $websiteClient */
        $websiteClient = app(MagentoWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://www.simple24.pl', [
            RequestOptions::FORM_PARAMS => []
        ], 'GET');
    }
}

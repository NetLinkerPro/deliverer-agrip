<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\ComarchWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class ComarchWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var ComarchWebsiteClient $websiteClient */
        $websiteClient = app(ComarchWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.pl/produkty,2');
        $this->assertStringContainsString('<span class="va-mid-ui line-height-1-ui">Zaloguj się</span>', $content);
        $this->assertStringNotContainsString('<li class="current-user-ui f-right-ui">', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var ComarchWebsiteClient $websiteClient */
        $websiteClient = app(ComarchWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContent('https://agrip.pl/produkty,2');
        $content = $websiteClient->getContent('https://agrip.pl/produkty,2');
        $this->assertStringContainsString('<li class="current-user-ui f-right-ui">', $content);
        $this->assertStringNotContainsString('<span class="va-mid-ui line-height-1-ui">Zaloguj się</span>', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
       // Artisan::call('cache:clear');
        /** @var ComarchWebsiteClient $websiteClient */
        $websiteClient = app(ComarchWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://agrip.pl/produkty/bezpieczenstwo,2,15?pageId=2&__template=products%2Fproducts-list.html&__include=', [],'GET');
        $this->assertStringContainsString('{"template":"', $content);
    }
}

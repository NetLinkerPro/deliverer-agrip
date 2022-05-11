<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NginxWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class NginxWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var NginxWebsiteClient $websiteClient */
        $websiteClient = app(NginxWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl');
        $this->assertStringContainsString('<input type="text" id="log_email" name="logowanie[0]"', $content);
        $this->assertStringNotContainsString('Osoba zalogowana:', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var NginxWebsiteClient $websiteClient */
        $websiteClient = app(NginxWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContent('https://b2b.agrip.pl');
        $content = $websiteClient->getContent('https://b2b.agrip.pl');
        $this->assertStringContainsString('<td class="opis">Osoba zalogowana:</td>', $content);
        $this->assertStringNotContainsString('<input type="text" id="log_email" name="logowanie[0]"', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
       // Artisan::call('cache:clear');
        /** @var NginxWebsiteClient $websiteClient */
        $websiteClient = app(NginxWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://agrip.pl/produkty/bezpieczenstwo,2,15?pageId=2&__template=products%2Fproducts-list.html&__include=', [],'GET');
        $this->assertStringContainsString('{"template":"', $content);
    }
}

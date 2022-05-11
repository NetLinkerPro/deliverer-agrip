<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class PhpWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var PhpWebsiteClient $websiteClient */
        $websiteClient = app(PhpWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.pl');
        $this->assertStringContainsString('Zaloguj się', $content);
        $this->assertStringNotContainsString('<a href="/moje-dane/">Moje dane</a>', $content);
        $this->assertStringNotContainsString("<a href='/moje-dane/'>Moje dane</a>", $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var PhpWebsiteClient $websiteClient */
        $websiteClient = app(PhpWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContent('https://agrip.pl');
        $content = $websiteClient->getContent('https://agrip.pl');
        $this->assertTrue(Str::contains($content, '<a href="/moje-dane/">Moje dane</a>')|| Str::contains($content, "<a href='/moje-dane/'>Moje dane</a>"));
        $this->assertStringNotContainsString('Zaloguj się', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
       // Artisan::call('cache:clear');
        /** @var PhpWebsiteClient $websiteClient */
        $websiteClient = app(PhpWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://agrip.pl/produkty/bezpieczenstwo,2,15?pageId=2&__template=products%2Fproducts-list.html&__include=', [],'GET');
        $this->assertStringContainsString('{"template":"', $content);
    }
}

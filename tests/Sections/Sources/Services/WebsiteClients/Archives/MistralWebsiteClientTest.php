<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MistralWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MistralWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var MistralWebsiteClient $websiteClient */
        $websiteClient = app(MistralWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://www.hurt.aw-narzedzia.com.pl/Produkty.aspx');
        $this->assertStringContainsString('<div id="kontenerLogowanie"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
//        Artisan::call('cache:clear');
        /** @var MistralWebsiteClient $websiteClient */
        $websiteClient = app(MistralWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
//        $websiteClient->getContents('https://www.hurt.aw-narzedzia.com.pl/Produkty.aspx');
        $content = $websiteClient->getContents('https://www.hurt.aw-narzedzia.com.pl/Produkty.aspx');
        $this->assertStringContainsString("id='belkaGornaNL_zalogowany'", $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var MistralWebsiteClient $websiteClient */
        $websiteClient = app(MistralWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://www.hurt.aw-narzedzia.com.pl/Kategorie.aspx', [
            RequestOptions::FORM_PARAMS => []
        ], 'GET');
    }
}

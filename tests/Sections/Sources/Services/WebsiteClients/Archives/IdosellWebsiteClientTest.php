<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AutografB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\IdosellWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NginxWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class IdosellWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var IdosellWebsiteClient $websiteClient */
        $websiteClient = app(IdosellWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.pl/');
        $this->assertStringNotContainsString('{"userId":', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var IdosellWebsiteClient $websiteClient */
        $websiteClient = app(IdosellWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContent('https://agrip.pl/');
        $content = $websiteClient->getContent('https://agrip.pl/');
        $this->assertStringContainsString('{"userId":', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
         Artisan::call('cache:clear');
        /** @var IdosellWebsiteClient $websiteClient */
        $websiteClient = app(IdosellWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://agrip.pl/pol_m_Oswietlenie-wewnetrzne-275.html?counter=2', [], 'GET');
        $this->assertStringContainsString('{"userId":', $content);
    }
}

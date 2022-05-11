<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SymfonyWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SymfonyWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var SymfonyWebsiteClient $websiteClient */
        $websiteClient = app(SymfonyWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.de/katalog');
        $this->assertStringContainsString('<a class="user-logon" href="/login">zaloguj</a>', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        /** @var SymfonyWebsiteClient $websiteClient */
        $websiteClient = app(SymfonyWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContent('https://agrip.de/katalog');
        $this->assertStringContainsString('<a class="user" href="/logout">wyloguj</a>', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        /** @var SymfonyWebsiteClient $websiteClient */
        $websiteClient = app(SymfonyWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://www.agrip.pl/katalog/availView', [
            RequestOptions::FORM_PARAMS=>[
                'val'=>true,
                'id_kategorii' => '-1',
                'page' =>2,
            ]
        ]);
        $this->assertStringContainsString('<a href="/goto/2" class="active">2</a>', $content);
    }
}

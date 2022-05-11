<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Prestashop2WebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Prestashop2WebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var Prestashop2WebsiteClient $websiteClient */
        $websiteClient = app(Prestashop2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl');
        $this->assertStringContainsString('<span class="title">Zaloguj się</span>', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var Prestashop2WebsiteClient $websiteClient */
        $websiteClient = app(Prestashop2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContents('https://b2b.agrip.pl/');
        $this->assertStringContainsString(Prestashop2WebsiteClient::AUTHORIZED_CONTENTS, $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        /** @var Prestashop2WebsiteClient $websiteClient */
        $websiteClient = app(Prestashop2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://b2b.agrip.pl/apple/?page=8&from-xhr',
        [

        ], 'GET');
        $this->assertStringContainsString('data-button-action=\"add-to-cart\"', $content);
    }
}

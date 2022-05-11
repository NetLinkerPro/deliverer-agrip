<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AspWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AspWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var AspWebsiteClient $websiteClient */
        $websiteClient = app(AspWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.net.pl/');
        $this->assertStringContainsString('width m-b">Zaloguj się</button>', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        /** @var AspWebsiteClient $websiteClient */
        $websiteClient = app(AspWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContent('https://b2b.agrip.net.pl');
        $this->assertStringContainsString('javascript:document.getElementById(\'logoutForm\').submit()', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        /** @var AspWebsiteClient $websiteClient */
        $websiteClient = app(AspWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://b2b.agrip.net.pl/Category/GetCategories', [
            RequestOptions::FORM_PARAMS=>[
                'parentId'=>743,
            ]
        ]);
        $this->assertStringContainsString('"Success":true,', $content);
    }
}

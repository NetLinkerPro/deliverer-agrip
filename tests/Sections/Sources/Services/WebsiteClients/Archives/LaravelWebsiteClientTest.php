<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\LaravelWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class LaravelWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var LaravelWebsiteClient $websiteClient */
        $websiteClient = app(LaravelWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' =>env('LOGIN2'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl/');
        $this->assertStringContainsString('<input type="text" name="contractor_code"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var LaravelWebsiteClient $websiteClient */
        $websiteClient = app(LaravelWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' =>env('LOGIN2'),
        ]);
        $websiteClient->getContents('https://b2b.agrip.pl/');
        $content = $websiteClient->getContents('https://b2b.agrip.pl/');
        $this->assertStringContainsString('<i class="fa fa-user"></i>', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
//        $this->expectException(Exception::class);
        /** @var LaravelWebsiteClient $websiteClient */
        $websiteClient = app(LaravelWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
            'login2' =>env('LOGIN2'),
        ]);
        $websiteClient->getContentAjax('https://b2b.agrip.pl/product/312', [
            RequestOptions::FORM_PARAMS => []
        ], 'GET', '{"product":{"id');
        $this->assertTrue(true);
    }
}

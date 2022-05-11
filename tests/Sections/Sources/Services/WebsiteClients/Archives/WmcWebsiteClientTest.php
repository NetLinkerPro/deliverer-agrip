<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WmcWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class WmcWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var WmcWebsiteClient $websiteClient */
        $websiteClient = app(WmcWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl/login');
        $this->assertStringContainsString('<input type="text" name="_username" id="inputEmail"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var WmcWebsiteClient $websiteClient */
        $websiteClient = app(WmcWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://b2b.agrip.pl/dashboard');
        $content = $websiteClient->getContents('https://b2b.agrip.pl/dashboard');
        $this->assertStringContainsString('<i class="icon icon-person"></i>', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
//        $this->expectException(Exception::class);
        /** @var WmcWebsiteClient $websiteClient */
        $websiteClient = app(WmcWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://b2b.agrip.pl/wmc/product/product/1/show', [
            RequestOptions::FORM_PARAMS => []
        ], 'GET', '<div class="col-md-12 product-show');
        $this->assertTrue(true);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MagentoWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\WoocommerceWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class WoocommerceWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var WoocommerceWebsiteClient $websiteClient */
        $websiteClient = app(WoocommerceWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://www.agrip.pl/');
        $this->assertStringContainsString("'logged_in': 'no'", $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var WoocommerceWebsiteClient $websiteClient */
        $websiteClient = app(WoocommerceWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://www.agrip.pl/');
        $content = $websiteClient->getContents('https://www.agrip.pl/');
        $this->assertStringContainsString("'logged_in': 'yes'", $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var WoocommerceWebsiteClient $websiteClient */
        $websiteClient = app(WoocommerceWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://www.simple24.pl', [
            RequestOptions::FORM_PARAMS => []
        ], 'GET');
    }
}

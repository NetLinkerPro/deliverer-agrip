<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AbstoreWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AbstoreWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var AbstoreWebsiteClient $websiteClient */
        $websiteClient = app(AbstoreWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.abstore.pl/');
        $this->assertStringContainsString('<a href="/client/loginorcreate/login/" ', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var AbstoreWebsiteClient $websiteClient */
        $websiteClient = app(AbstoreWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://agrip.abstore.pl');
        $content = $websiteClient->getContents('https://agrip.abstore.pl/');
        $this->assertStringContainsString('<a href="/client/account/index/" ', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var AbstoreWebsiteClient $websiteClient */
        $websiteClient = app(AbstoreWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://agrip.abstore.pl/ajax/fts/navigationstep', [
            RequestOptions::FORM_PARAMS => []
        ]);
    }
}

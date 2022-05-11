<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SupremisB2bWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var SupremisB2bWebsiteClient $websiteClient */
        $websiteClient = app(SupremisB2bWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://www.agrip-b2b.com.pl');
        $this->assertStringContainsString('<div id="niezalogowany" class="login">', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        /** @var SupremisB2bWebsiteClient $websiteClient */
        $websiteClient = app(SupremisB2bWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContent('https://www.agrip-b2b.com.pl');
        $this->assertStringContainsString('<div id="zalogowany" class="login">', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var SupremisB2bWebsiteClient $websiteClient */
        $websiteClient = app(SupremisB2bWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://www.agrip-b2b.com.pl', [
            RequestOptions::FORM_PARAMS => []
        ]);
    }
}

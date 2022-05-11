<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SupremisWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var SupremisWebsiteClient $websiteClient */
        $websiteClient = app(SupremisWebsiteClient::class);
        $content = $websiteClient->getContentAnonymous('https://www.agrip.com.pl');
        $this->assertStringContainsString('<div class="ui-header">LOGOWANIE DO B2B</div>', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var SupremisWebsiteClient $websiteClient */
        $websiteClient = app(SupremisWebsiteClient::class);
        $content = $websiteClient->getContent('https://www.agrip.com.pl');
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var SupremisWebsiteClient $websiteClient */
        $websiteClient = app(SupremisWebsiteClient::class);
        $websiteClient->getContentAjax('https://www.agrip.com.pl', [
            RequestOptions::FORM_PARAMS => []
        ]);
    }
}

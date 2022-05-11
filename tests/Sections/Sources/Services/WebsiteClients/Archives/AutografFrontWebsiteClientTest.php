<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AutografFrontWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NginxWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AutografFrontWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var AutografFrontWebsiteClient $websiteClient */
        $websiteClient = app(AutografFrontWebsiteClient::class);
        $content = $websiteClient->getContentAnonymous('https://agrip.pl/oferta/haki-holownicze');
        $this->assertStringContainsString('Blisko 1500 referencji ', $content);
    }
}

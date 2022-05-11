<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives\Magento2WebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Magento2WebsiteClientTest extends TestCase
{
    public function testContent()
    {
        /** @var Magento2WebsiteClient $service */
        $service = app(Magento2WebsiteClient::class);
        $content = $service->getContentAnonymous('https://agrip.de');
        $this->assertNotEmpty($content);
    }
}

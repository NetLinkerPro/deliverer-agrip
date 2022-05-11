<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives\Dedicated1WebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Dedicated1WebsiteClientTest extends TestCase
{
    public function testWebsiteClient()
    {
        /** @var Dedicated1WebsiteClient $websiteClient */
        $websiteClient = app(Dedicated1WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContents('https://sklep.agrip.pl/pl-pl');
        $this->assertContains('Zalogowany jako:', $content);
    }
}

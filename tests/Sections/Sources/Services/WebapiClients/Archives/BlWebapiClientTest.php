<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebapiClients\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\BlWebapiClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class BlWebapiClientTest extends TestCase
{
    public function testSendRequestApi()
    {
        /** @var BlWebapiClient $webapiClient */
        $webapiClient = app(BlWebapiClient::class, [
            'token' => env('TOKEN'),
        ]);
        $sessionKey = $webapiClient->sendRequest('getInventoryCategories', [
            'inventory_id' =>1154,
        ]);
        $this->assertNotEmpty($sessionKey);
    }
}

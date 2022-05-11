<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebapiClients\Archives;

use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives\SoapWebapiClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SoapWebapiClientTest extends TestCase
{
    public function testSendRequestApi()
    {
        /** @var SoapWebapiClient $webapiClient */
        $webapiClient = app(SoapWebapiClient::class, [
            'token' => env('TOKEN'),
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $sessionKey = $webapiClient->getSessionKey();
        $this->assertNotEmpty($sessionKey);
    }
}

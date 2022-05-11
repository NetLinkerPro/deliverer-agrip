<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Comarch2WebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Comarch2WebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var Comarch2WebsiteClient $websiteClient */
        $websiteClient = app(Comarch2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'login2' => env('LOGIN2'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl/');
        $this->assertStringContainsString('<meta name="theme-color"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var Comarch2WebsiteClient $websiteClient */
        $websiteClient = app(Comarch2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'login2' => env('LOGIN2'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://b2b.agrip.pl/api/configuration/getforcustomer');
        $content = $websiteClient->getContents('https://b2b.agrip.pl/api/configuration/getforcustomer');
        $this->assertStringContainsString('{"precision":1,"warehouseId":1,"', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
       // Artisan::call('cache:clear');
        /** @var Comarch2WebsiteClient $websiteClient */
        $websiteClient = app(Comarch2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'login2' => env('LOGIN2'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://b2b.agrip.pl/api/configuration/getforcustomer', [],'GET', '{"precision":1,"warehouseId":1,"');
        $this->assertStringContainsString('{"precision":1,"warehouseId":1,"', $content);
    }
}

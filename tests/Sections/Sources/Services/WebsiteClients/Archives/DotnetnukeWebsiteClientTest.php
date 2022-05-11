<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\DotnetnukeWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class DotnetnukeWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var DotnetnukeWebsiteClient $websiteClient */
        $websiteClient = app(DotnetnukeWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://www.argip.com.pl/Home.aspx');
        $this->assertStringContainsString(DotnetnukeWebsiteClient::VALID_CONTENT_ANONYMOUS, $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var DotnetnukeWebsiteClient $websiteClient */
        $websiteClient = app(DotnetnukeWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://www.argip.com.pl/Home.aspx');
        $content = $websiteClient->getContents('https://www.argip.com.pl/Home.aspx');
        $this->assertStringContainsString(DotnetnukeWebsiteClient::VALID_CONTENT_LOGGED, $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
       // Artisan::call('cache:clear');
        /** @var DotnetnukeWebsiteClient $websiteClient */
        $websiteClient = app(DotnetnukeWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('http://www.b2b.agrip.info/api/items/pricesasync/?articleId=86239&warehouseId=1&features=', [],'GET', 'itemExistsInCurrentPriceList');
        $content = $websiteClient->getContentAjax('http://www.b2b.agrip.info/api/configuration/getforcustomer', [],'GET', '{"precision":1,"warehouseId');
        $this->assertStringContainsString('{"precision":1,"warehouseId', $content);
    }
}

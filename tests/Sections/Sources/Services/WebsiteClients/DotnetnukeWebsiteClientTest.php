<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients;

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
        $content = $websiteClient->getContentAjax('https://www.argip.com.pl/Produkty/Zakupy/tabid/85/parentid/77/product/nity-nitonakretki/Default.aspx');
        $content = $websiteClient->getContentAjax('https://www.argip.com.pl/Produkty/Zakupy/tabid/85/parentid/77/product/nity-nitonakretki/Default.aspx');
        $this->assertStringContainsString(DotnetnukeWebsiteClient::VALID_CONTENT_AJAX, $content);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\CorpsWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class CorpsWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var CorpsWebsiteClient $websiteClient */
        $websiteClient = app(CorpsWebsiteClient::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl/produkty-do-kategorii/1040');
        $this->assertStringContainsString('login-form-wrapper', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var CorpsWebsiteClient $websiteClient */
        $websiteClient = app(CorpsWebsiteClient::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $content = $websiteClient->getContent('https://b2b.agrip.pl/produkt/ET174R');
        $this->assertStringContainsString('id="user-dropdown-menu"', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
       // Artisan::call('cache:clear');
        /** @var CorpsWebsiteClient $websiteClient */
        $websiteClient = app(CorpsWebsiteClient::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $content = $websiteClient->getContentAjax('https://b2b.agrip.pl/ajax/wybierz-logo', [],'GET');
        $this->assertStringContainsString('"metadata":{"status":"ok"', $content);
    }
}

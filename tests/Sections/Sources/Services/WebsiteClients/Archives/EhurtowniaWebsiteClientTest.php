<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\EhurtowniaWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class EhurtowniaWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var EhurtowniaWebsiteClient $websiteClient */
        $websiteClient = app(EhurtowniaWebsiteClient::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.ehurtownia.pl');
        $this->assertStringContainsString('<app-root></app-root>', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        /** @var EhurtowniaWebsiteClient $websiteClient */
        $websiteClient = app(EhurtowniaWebsiteClient::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $content = $websiteClient->getContent('https://agrip.ehurtownia.pl');
        $this->assertStringContainsString('<app-root></app-root>', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var EhurtowniaWebsiteClient $websiteClient */
        $websiteClient = app(EhurtowniaWebsiteClient::class, [
            'login' => env('LOGIN2'),
            'password' => env('PASS2'),
        ]);
        $content = $websiteClient->getContentAjax('https://agrip.ehurtownia.pl/eh-one-backend/rest/47/1/2135870/oferta?lang=PL&offset=0&limit=20&sortAsc=indeks', [],'GET');
        $this->assertStringContainsString('countPozycjiOferty', $content);
    }
}

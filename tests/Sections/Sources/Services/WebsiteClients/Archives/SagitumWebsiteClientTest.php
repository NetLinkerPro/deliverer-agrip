<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SagitumWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SupremisWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class SagitumWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var SagitumWebsiteClient $websiteClient */
        $websiteClient = app(SagitumWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://b2b.agrip.pl');
        $this->assertStringContainsString('id="ContentPlaceHolder1_txtLogin_ET"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var SagitumWebsiteClient $websiteClient */
        $websiteClient = app(SagitumWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContents('https://b2b.agrip.pl/Forms/Newsletter.aspx');
        $this->assertStringContainsString('<a id="cmdWyloguj" class="wyloguj" href="javascript', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        $this->expectException(Exception::class);
        /** @var SagitumWebsiteClient $websiteClient */
        $websiteClient = app(SagitumWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContentAjax('https://b2b.agrip.pl', [
            RequestOptions::FORM_PARAMS => []
        ]);
    }
}

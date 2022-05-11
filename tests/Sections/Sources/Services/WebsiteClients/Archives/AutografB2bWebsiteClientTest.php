<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\AutografB2bWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NginxWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\PhpWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AutografB2bWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var AutografB2bWebsiteClient $websiteClient */
        $websiteClient = app(AutografB2bWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('http://b2b.agrip.pl');
        $this->assertStringContainsString('<META HTTP-EQUIV="REFRESH" CONTENT="0; URL=/e-zamowienia-www">', $content);
        $this->assertStringNotContainsString('<div class="user_link_panel darkFontColor">', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var AutografB2bWebsiteClient $websiteClient */
        $websiteClient = app(AutografB2bWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContent('http://b2b.agrip.pl/e-zamowienia-www/app/');
        $content = $websiteClient->getContent('http://b2b.agrip.pl/e-zamowienia-www/app/');
        $this->assertStringContainsString('<div class="user_welcome_msg darkFontColor">', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
         Artisan::call('cache:clear');
        /** @var AutografB2bWebsiteClient $websiteClient */
        $websiteClient = app(AutografB2bWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $offset = 30;
        $perPage = 15;
        $params = [
            'javax.faces.partial.ajax' => 'true',
            'javax.faces.source' => 'productForm-dgProducts',
            'javax.faces.partial.execute' => 'productForm-dgProducts',
            'javax.faces.partial.render' => 'productForm-dgProducts',
            'javax.faces.behavior.event' => 'page',
            'javax.faces.partial.event' => 'page',
            'productForm-dgProducts_pagination' => 'true',
            'productForm-dgProducts_first' =>$offset,
            'productForm-dgProducts_rows' => $perPage,
            'productForm' => 'productForm',
            'productForm-j_idt336_focus' => '',
            'productForm-j_idt336_input' => 'i18nname_asc',
            'productForm-tbProducts_activeIndex' => 1,
            'productForm-dgProducts_rppDD' => $perPage,
            'javax.faces.ViewState' => $websiteClient->getViewState(),
        ];
        $content = $websiteClient->getContentAjax('http://b2b.agrip.pl/e-zamowienia-www/app/produkty/0',[
            RequestOptions::FORM_PARAMS =>$params,
        ], 'POST');
        $this->assertStringContainsString('<update id="javax.faces.ViewState"><![CDATA[', $content);
    }
}

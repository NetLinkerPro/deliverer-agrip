<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\MagresnetWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class MagresnetWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var MagresnetWebsiteClient $websiteClient */
        $websiteClient = app(MagresnetWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('http://212.180.197.238/OfertaMobile.aspx');
        $this->assertStringContainsString('<input name="Login1$UserName" type="text" id="Login1_UserName"', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var MagresnetWebsiteClient $websiteClient */
        $websiteClient = app(MagresnetWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContents('http://212.180.197.238/OfertaMobile.aspx');
        $this->assertStringContainsString('ctl00$ContentPlaceHolder1$btnProducent', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        /** @var MagresnetWebsiteClient $websiteClient */
        $websiteClient = app(MagresnetWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $dataAjax = $websiteClient->getLastDataAspx();
        $contents = $websiteClient->getContentAjax('http://212.180.197.238/OfertaMobile.aspx', [
            RequestOptions::FORM_PARAMS => [
                'ctl00$ToolkitScriptManager1' => 'ctl00$ContentPlaceHolder1$UpdatePanel1|ctl00$ContentPlaceHolder1$lvGrupy$DataPager1$ctl00$ctl00',
                'ctl00$ContentPlaceHolder1$ddlMagazyn' => '3',
                'ctl00$ContentPlaceHolder1$ddlCeny' => '5',
                'ctl00$ContentPlaceHolder1$ddlKategoria' => '0',
                'ctl00$ContentPlaceHolder1$txtName' => '',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl02$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl03$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl04$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl05$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl06$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl07$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl08$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl09$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl10$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl11$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl12$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$gvTowary$ctl13$txtIlosc' => '0',
                'ctl00$ContentPlaceHolder1$hfPageIndex' => '0',
                'ctl00$ContentPlaceHolder1$hfPageSize' => '60',
                'ctl00$ContentPlaceHolder1$hfProducent' => '0',
                'ctl00$ContentPlaceHolder1$hfAsortyment' => '0',
                'ctl00$ContentPlaceHolder1$hfOfrid' => '0',
                'ctl00$ContentPlaceHolder1$hfHanId' => '0',
                'ctl00$ContentPlaceHolder1$hfImage' => '0',
                'ctl00$ContentPlaceHolder1$hfOrderId' => '0',
                'ctl00$ContentPlaceHolder1$hfKntId' => '23723',
                'ctl00$ContentPlaceHolder1$hfDkrId' => '35',
                'hiddenInputToUpdateATBuffer_CommonToolkitScripts' => '1',
                '__EVENTTARGET' => $dataAjax['event_target'],
                '__EVENTARGUMENT' => $dataAjax['event_argument'],
                '__LASTFOCUS' => '',
                '__VIEWSTATE' =>$dataAjax['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAjax['view_state_generator'],
                '__PREVIOUSPAGE' => $dataAjax['previous_page'],
                '__EVENTVALIDATION' => $dataAjax['event_validation'],
                '__VIEWSTATEENCRYPTED' => $dataAjax['view_state_encrypted'],
                '__ASYNCPOST' =>$dataAjax['async_post'],
                'ctl00$ContentPlaceHolder1$lvGrupy$DataPager1$ctl00$ctl00' => '1',
            ]
        ]);
        $this->assertStringContainsString('<style type="text/css">', $contents);
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\NodeWebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class NodeWebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var NodeWebsiteClient $websiteClient */
        $websiteClient = app(NodeWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://agrip.pl');
        $this->assertStringNotContainsString('<div id="divLogout">', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var NodeWebsiteClient $websiteClient */
        $websiteClient = app(NodeWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $websiteClient->getContents('https://agrip.pl/');
        $content = $websiteClient->getContents('https://agrip.pl/');
        $this->assertStringContainsString('<div id="divLogout">', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var NodeWebsiteClient $websiteClient */
        $websiteClient = app(NodeWebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://www.agrip.pl/index.php', [
           RequestOptions::FORM_PARAMS=>[
               'products_action' => 'ajax_category',
               'is_ajax' => '1',
               'ajax_type' => 'json',
               'url' => 'https://www.agrip.pl/offer/pl/_/#/list/?gr=19299&p=2&srch=&pproducers=&v=t&s=name&sd=a&st=s&tp=3.2.0',
               'locale_ajax_lang' => 'pl',
               'products_ajax_group' => '19299',
               'products_ajax_search' => '',
               'products_ajax_page' => '2',
               'products_ajax_view' => 't',
               'products_ajax_stock' => 's',
               'products_ajax_sort' => 'name',
               'products_ajax_sort_dir' => 'a',
               'products_ajax_filter' => '{"srch":""}',
               'products_ajax_filter_html' => '0',
               'products_ajax_csv_export' => '0',
               'products_ajax_use_desc_index' => '1',
           ]
        ]);
        $this->assertStringContainsString('[{"type":', $content);
    }
}

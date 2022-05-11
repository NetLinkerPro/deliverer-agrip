<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\WebsiteClients\Archives;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Asp2WebsiteClient;
use NetLinker\DelivererAgrip\Tests\TestCase;

class Asp2WebsiteClientTest extends TestCase
{
    public function testAnonymousRequestWebsiteClient()
    {
        /** @var Asp2WebsiteClient $websiteClient */
        $websiteClient = app(Asp2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAnonymous('https://www.agrip.com/pl-pl');
        $this->assertStringContainsString('class="whitespace-no-wrap">Zaloguj się</span>', $content);
    }

    public function testRequestLoginWebsiteClient()
    {
        Artisan::call('cache:clear');
        /** @var Asp2WebsiteClient $websiteClient */
        $websiteClient = app(Asp2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContents('https://www.agrip.com/pl-pl');
        $this->assertStringContainsString('class="whitespace-no-wrap">Moje konto</span>', $content);
    }

    public function testRequestLoginAjaxWebsiteClient()
    {
        /** @var Asp2WebsiteClient $websiteClient */
        $websiteClient = app(Asp2WebsiteClient::class, [
            'login' => env('LOGIN'),
            'password' => env('PASS'),
        ]);
        $content = $websiteClient->getContentAjax('https://api.agrip.com/api/Price/Simple?itemIds=01AV405&itemIds=0A36262&itemIds=0B46998&itemIds=1W2Y2&itemIds=2X39G&itemIds=3VC9Y&itemIds=40AF0135EU&itemIds=40ANY230EU&itemIds=43NY4&itemIds=452-BCYH&itemIds=470-ABRY&itemIds=4X50M08812&itemIds=5D91C&itemIds=741727-001&itemIds=800513-001&itemIds=854108-850&itemIds=DELL-WD19TB&itemIds=FPT1C&itemIds=G8VCF&itemIds=GG4FM&itemIds=H6Y90AA&itemIds=MBXHP-BA0201&itemIds=MGJN9&itemIds=RDYCT&itemIds=RNP72&itemIds=T8YYD&itemIds=TP1GT&itemIds=TWCPG&itemIds=W125707609&itemIds=W125790694',
        [], 'GET');
        $this->assertStringContainsString(',"isSuccess":true,', $content);
    }
}

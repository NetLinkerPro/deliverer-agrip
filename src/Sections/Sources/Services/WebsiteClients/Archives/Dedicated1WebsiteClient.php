<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\ResponseInterface;

class Dedicated1WebsiteClient implements WebsiteClient
{
    use CrawlerHtml;

    /** @var string $login */
    protected $login;

    /** @var string $password */
    protected $password;

    /**
     * Dedicated1WebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Get content anonymous
     *
     * @param string $url
     * @param array $options
     * @return string
     */
    public function getContentAnonymous(string $url, array $options = []): string
    {
        $response = $this->getResponseAnonymous($url, $options);
        return $response->getBody()->getContents();
    }

    /**
     * Get response anonymous
     *
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     */
    private function getResponseAnonymous(string $url, array $options): ResponseInterface
    {
        $client = $this->getClientAnonymous();
        DelivererLogger::log(sprintf('Get response anonymous %s', $url));
        return $client->get($url, $options);
    }

    /**
     * Get client anonymous
     *
     * @return Client
     */
    private function getClientAnonymous(): Client
    {
        return new Client(['verify' => false, 'cookies' => true]);
    }

    /**
     * Get content
     *
     * @param string $url
     * @param array $options
     * @return string
     * @throws DelivererAgripException
     */
    public function getContents(string $url, array $options = []): string
    {
        $client = $this->getClient();
        $response = $client->get($url, $options);
        $content = $response->getBody()->getContents();
        if (!Str::contains($content, 'div class="zllog mbut bef">Zalogowany jako: ')){
            throw new DelivererAgripException('Content is not authorized.');
        }
        return $content;
    }

    /**
     * Get client
     *
     * @return Client
     * @throws DelivererAgripException
     */
    private function getClient(): Client
    {
        $cookiesLogin = $this->getCookiesLogin();
        return new Client(['verify' => false, 'cookies' => $cookiesLogin]);
    }

    /**
     * Get cookies login
     *
     * @return CookieJar
     */
    private function getCookiesLogin(): CookieJar
    {
        $keyCache = sprintf('deliverer-agrip_cookies_login_%s', $this->login);
        return Cache::remember($keyCache, 3600, function(){
            $client = $this->getClientAnonymous();
            $securityKey = $this->getSecurityKey($client);
            $response = $client->post('https://sklep.agrip.pl/pl-pl/login', [
                RequestOptions::FORM_PARAMS => [
                    'SecurityKey' => $securityKey,
                    'LoginRedirectTo' => 'https://sklep.agrip.pl/pl-pl/',
                    'Language' => 'pl-pl',
                    'LoginEmail' => $this->login,
                    'LoginPasswd' => $this->password,
                ],
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest'
                ]
            ]);
            $content = $response->getBody()->getContents();
            $dataResponse = json_decode($content, true);
            if ($dataResponse['errorCode'] !== 0){
                throw new DelivererAgripException('Failed login to Agrip');
            }
           return $client->getConfig('cookies');
        });
    }

    /**
     * Get security key
     *
     * @param Client $client
     * @return string
     */
    private function getSecurityKey(Client $client): string
    {
        $response = $client->post('https://sklep.agrip.pl/pl-pl/ajax/ajax_logowanie', [
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $crawler = $this->getCrawler($content);
        return $this->getAttributeCrawler($crawler->filter('input[name="SecurityKey"]'), 'value');
    }
}
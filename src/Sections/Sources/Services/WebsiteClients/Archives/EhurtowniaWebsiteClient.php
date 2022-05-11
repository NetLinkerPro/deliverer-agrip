<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\ResponseInterface;

class EhurtowniaWebsiteClient implements WebsiteClient
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
        $options = $this->getDefaultOptionsRequest($options);
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
        DelivererLogger::log(sprintf('Get content %s', $url));
        $options = $this->getDefaultOptionsRequest($options);
        $response = $client->get($url, $options);
        $content = $response->getBody()->getContents();
        if (!$this->isLoggedClient($client)){
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
        return Cache::remember($keyCache, 590, function(){
            $client = $this->getClientAnonymous();
            $dataRequest = $this->getDataRequest($client);
            $options = $this->getDefaultOptionsRequest([
                RequestOptions::FORM_PARAMS => [
                    'username' => $this->login,
                    'password' => $this->password,
                    'login' => 'Zaloguj się',
                ],
            ]);
            $options[ 'on_stats'] = function (TransferStats $stats) use (&$url) {
                $url = $stats->getEffectiveUri();
            };
            $response = $client->post($dataRequest['url'], $options);
            $response->getBody()->getContents();
            if (!$this->isLoggedClient($client)){
                throw new DelivererAgripException('Failed login to Agrip');
            }
           return $client->getConfig('cookies');
        });
    }

    /**
     * Get data request
     *
     * @param Client $client
     * @return array
     */
    private function getDataRequest(Client $client): array
    {
        $options = $this->getDefaultOptionsRequest();
        $response = $client->get('https://sso.infinite.pl/auth/realms/InfiniteEH/protocol/openid-connect/auth?client_id=ehurtownia-panel-frontend&redirect_uri=https%3A%2F%2Fagrip.ehurtownia.pl%2F%3Fredirect_fragment%3D%252Finformacja&response_mode=fragment&response_type=code&scope=openid',$options);
        $content = $response->getBody()->getContents();
        $crawler = $this->getCrawler($content);
        return [
            'url'=>$this->getAttributeCrawler($crawler->filter('form[id="kc-form-login"]'), 'action'),
        ];
    }

    /**
     * Get content AJAX
     *
     * @param string $url
     * @param array $options
     * @param string $method
     * @param string $contentValid
     * @return string
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getContentAjax(string $url, array $options = [], string $method='POST', string $contentValid = 'countPozycjiOferty'): string{
        $client = $this->getClientAnonymous();
        $token = $this->getTokenAjax($client);
        $options = array_merge_recursive($options, [
            'headers'=>[
                'Content-Type' =>'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
        DelivererLogger::log(sprintf('Get content AJAX %s', $url));
        $options = $this->getDefaultOptionsRequest($options);
        $response = $client->request($method, $url, $options);
        $content = $response->getBody()->getContents();
        if (!Str::contains($content, $content)){
            throw new DelivererAgripException('Content is not valid.');
        }
        return $content;
    }

    /**
     * Get default options request
     *
     * @param array $options
     * @return array
     */
    private function getDefaultOptionsRequest(array $options = []): array
    {
       return array_merge_recursive([
            'headers' => [
                'user-agent' =>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
            ]
        ], $options);
    }

    /**
     * Is logged client
     *
     * @param Client $client
     * @return bool
     */
    private function isLoggedClient(Client $client): bool
    {
        $cookies = $client->getConfig('cookies');
        /** @var SetCookie $cookie */
        foreach ($cookies as $cookie){
            if ($cookie->getName() === 'KEYCLOAK_IDENTITY'){
                return true;
            }
        }
        return false;
    }

    /**
     * Get token Ajax
     *
     * @param Client $client
     * @return string
     */
    private function getTokenAjax(Client $client): string
    {
        $keyCache = sprintf('%s_token-ajax_%s', get_class($this), $this->login);
        return Cache::remember($keyCache, 590, function() use (&$client){
            $code = $this->getCode();
            $url = 'https://sso.infinite.pl/auth/realms/InfiniteEH/protocol/openid-connect/token';
            $options = $this->getDefaultOptionsRequest([
                RequestOptions::FORM_PARAMS => [
                    'code' => $code,
                    'grant_type' =>'authorization_code',
                    'client_id' => 'ehurtownia-panel-frontend',
                    'redirect_uri' => 'https://agrip.ehurtownia.pl/?redirect_fragment=%2Finformacja',
                ],
            ]);
            $response = $client->post($url, $options);
            $content = $response->getBody()->getContents();
            $dataResponse = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
            return $dataResponse['access_token'];
        });
    }

    /**
     * Get code
     *
     * @return string
     * @throws DelivererAgripException
     */
    private function getCode(): string{
        $client = $this->getClientAnonymous();
        $dataRequest = $this->getDataRequest($client);
        $options = $this->getDefaultOptionsRequest([
            RequestOptions::FORM_PARAMS => [
                'username' => $this->login,
                'password' => $this->password,
                'login' => 'Zaloguj się',
            ],
        ]);
        $code = null;
        $options[ 'on_stats'] = function (TransferStats $stats) use (&$code) {
            $url = $stats->getEffectiveUri()->getFragment();
            if (Str::contains($url, '&code=')){
                $code = explode('&code=', $url)[1];
            }
        };
        $response = $client->post($dataRequest['url'], $options);
        $response->getBody()->getContents();
        if (!$code){
            throw new DelivererAgripException('Not get code.');
        }
        if (!$this->isLoggedClient($client)){
            throw new DelivererAgripException('Failed login to Agrip');
        }
        return $code;
    }
}
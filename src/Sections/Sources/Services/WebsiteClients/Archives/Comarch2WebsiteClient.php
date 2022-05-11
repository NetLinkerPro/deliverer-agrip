<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\ResponseInterface;

class Comarch2WebsiteClient implements WebsiteClient
{
    use CrawlerHtml;

    /** @var string $login */
    protected $login;

    /** @var string $password */
    protected $password;

    /** @var string $login2 */
    protected $login2;

    /**
     * Dedicated1WebsiteClient constructor
     *
     * @param string $login
     * @param string $password
     * @param string $login2
     */
    public function __construct(string $login, string $password, string $login2)
    {
        $this->login = $login;
        $this->login2 = $login2;
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
        $response = $client->get($url, $options);
        $content = $response->getBody()->getContents();
//        if (!$this->isLoggedClient($content)) {
//            throw new DelivererAgripException('Content is not authorized.');
//        }
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
        return new Client($this->getDefaultOptionsRequest(['verify' => false, 'cookies' => $cookiesLogin]));
    }

    /**
     * Get cookies login
     *
     * @return CookieJar
     */
    private function getCookiesLogin(): CookieJar
    {
        $keyCache = sprintf('deliverer-agrip_cookies_login_%s', $this->login);
        return Cache::remember($keyCache, 3600, function () {
            $client = $this->getClientAnonymous();
//            $csrfToken = $this->getCsrfToken($client);
            $options = $this->getDefaultOptionsRequestAjax([
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Referer' => 'https://b2b.agrip.pl/login',
                ],
                RequestOptions::JSON => [
                    'LoginConfirmation' => true,
                    'companyGroupId' => 0,
                    'customerName' => $this->login,
                    'password' => $this->password,
                    'rememberMe' => true,
                    'userName' =>$this->login2,
                ]
            ]);
            $response = $client->post('https://b2b.agrip.pl/account/login', $options);
            $contents = $response->getBody()->getContents();
            $client->get('https://b2b.agrip.pl/api/configuration/getforcustomer');
//            if (!$this->isLoggedClient($contents)) {
//                throw new DelivererAgripException('Not authorized content for Agrip');
//            }
            return $client->getConfig('cookies');
        });
    }

    /**
     * Get data request
     *
     * @param Client $client
     * @return string
     * @throws DelivererAgripException
     */
    private function getCsrfToken(Client $client): string
    {
        $response = $client->get('https://agrip.pl/logowanie,7', $this->getDefaultOptionsRequest());
        $content = $response->getBody()->getContents();
        $explodeContent = explode('CSRF=(function(z){return atob(z);})(\'', $content);
        $explodeContent = explode('\');', $explodeContent[1]);
        $token = $explodeContent[0];
        if (!$token) {
            throw new DelivererAgripException('Not found token.');
        }
        return base64_decode($token);
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
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = '{"template":"'): string
    {
        $client = $this->getClient();
        $options = $this->getDefaultOptionsRequestAjax($options);
        DelivererLogger::log(sprintf('Get content AJAX %s', $url));
        $response = $client->request($method, $url, $options);
        $content = $response->getBody()->getContents();
        if (!Str::contains($content, $contentValid)) {
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
                'Host'=>' b2b.agrip.pl',
                'User-Agent'=>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36',
                ],
        ], $options);
    }

    /**
     * Get default options request AJAX
     *
     * @param array $options
     * @return array
     */
    private function getDefaultOptionsRequestAjax(array $options = []): array
    {
        return array_merge_recursive([
            'headers' => ['Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
                'Connection' => 'keep-alive',
                'Host' => 'agrip.pl',
                'Origin' => 'https://agrip.pl',
                'sec-ch-ua' => '" Not;A Brand";v="99", "Google Chrome";v="91", "Chromium";v="91"',
                'sec-ch-ua-mobile' => '?0',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-origin',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36',
               'X-Requested-With' => 'XMLHttpRequest',
            ],
        ], $options);
    }

    /**
     * Is logged client
     *
     * @param string $contents
     * @return bool
     */
    private function isLoggedClient(string $contents): bool
    {
        return Str::contains($contents, '<li class="current-user-ui f-right-ui">');
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
        return Cache::remember($keyCache, 590, function () use (&$client) {
            $code = $this->getCode();
            https://b2b.ecolifegroup.pl/
            $url = 'https://sso.infinite.pl/auth/realms/InfiniteEH/protocol/openid-connect/token';
            $options = $this->getDefaultOptionsRequest([
                RequestOptions::FORM_PARAMS => [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
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
    private function getCode(): string
    {
        $client = $this->getClientAnonymous();
        $dataRequest = $this->getCsrfToken($client);
        $options = $this->getDefaultOptionsRequest([
            RequestOptions::FORM_PARAMS => [
                'username' => $this->login,
                'password' => $this->password,
                'login' => 'Zaloguj się',
            ],
        ]);
        $code = null;
        $options['on_stats'] = function (TransferStats $stats) use (&$code) {
            $url = $stats->getEffectiveUri()->getFragment();
            if (Str::contains($url, '&code=')) {
                $code = explode('&code=', $url)[1];
            }
        };
        $response = $client->post($dataRequest['url'], $options);
        $response->getBody()->getContents();
        if (!$code) {
            throw new DelivererAgripException('Not get code.');
        }
        if (!$this->isLoggedClient($client)) {
            throw new DelivererAgripException('Failed login to Agrip');
        }
        return $code;
    }

    /**
     * Get cookie client
     *
     * @param string $name
     * @param Client $client
     * @return string
     */
    private function getCookieClient(string $name, Client $client): ?string
    {
        $cookies = $client->getConfig('cookies');
        /** @var SetCookie $cookie */
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie->getValue();
            }
        }
       return null;
    }
}
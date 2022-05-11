<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WmcWebsiteClient implements WebsiteClient
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
        $configurations = ['verify' => false, 'cookies' => true];
        return new Client($configurations);
    }

    /**
     * Get content
     *
     * @param string $url
     * @param array $options
     * @param string|null $contentValid
     * @return string
     * @throws DelivererAgripException
     */
    public function getContents(string $url, array $options = [], string $contentValid = null): string
    {
        $client = $this->getClient();
        DelivererLogger::log(sprintf('Get content %s', $url));
        $response = $client->get($url, $options);
        $content = $response->getBody()->getContents();
        if ($contentValid){
            if (!Str::contains($content, $contentValid) && !Str::contains($content, 'JPEG')){
                throw new DelivererAgripException('Content is not authorized.');
            }
        } else if (!$this->isLoggedClient($content)) {
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
        return new Client($this->getDefaultOptionsRequest(['verify' => false, 'cookies' => $cookiesLogin]));
    }

    /**
     * Get cookies login
     *
     * @return CookieJar
     */
    private function getCookiesLogin(): CookieJar
    {
        $keyCache = sprintf('deliverer-agrip_cookies_login_2_%s', $this->login);
        return Cache::remember($keyCache, 3600, function () {
            $stack = new HandlerStack();
            $stack->setHandler(new CurlHandler());
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
                $size = $request->getBody()->getSize();
                return $request->withHeader('Content-Length', $size);
            }));
            $client = $this->getClientAnonymous();
            $dataLogin = $this->getDataLogin($client);
            $client = new Client(['verify' => false, 'cookies' => $dataLogin['cookies']]);
            $cook = $dataLogin['cookies']->toArray()[0]['Value'];
            $options = $this->getDefaultOptionsRequest([
                RequestOptions::FORM_PARAMS => [
                    '_username' => $this->login,
                    '_password' => $this->password,
                    '_csrf_token' => $dataLogin['token'],
                ],
                'headers' => [
                    'Cookie' => sprintf('PHPSESSID=%s', $cook),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'handler' => $stack,
            ]);
            $response = $client->post('https://b2b.agrip.pl/login_check', $options);
            $cookie = $response->getHeader('Set-Cookie');
            $explodeCookie = explode('=', $cookie[0], 2);
            $cookie = explode(';', $explodeCookie[1])[0];
            $cookieResponse = CookieJar::fromArray(['PHPSESSID' => $cookie], 'b2b.agrip.pl');
            $client = new Client(['verify' => false, 'cookies' => $cookieResponse]);
            $response = $client->get('https://b2b.agrip.pl/', $this->getDefaultOptionsRequest());
            $contents = $response->getBody()->getContents();
            if (!$this->isLoggedClient($contents)) {
                throw new DelivererAgripException('Not authorized content for Agrip');
            }
            return $client->getConfig('cookies');
        });
    }

    /**
     * Get data login
     *
     * @param Client $client
     * @return array
     * @throws DelivererAgripException
     */
    private function getDataLogin(Client $client): array
    {
        $response = $client->get('https://b2b.agrip.pl/login', $this->getDefaultOptionsRequest());
        $content = $response->getBody()->getContents();
        $crawler = $this->getCrawler($content);
        $token = $this->getAttributeCrawler($crawler->filter('input[name="_csrf_token"]'), 'value');
        if (!$token) {
            throw new DelivererAgripException('Not found token.');
        }
        $cookie = $response->getHeader('Set-Cookie');
        $explodeCookie = explode('=', $cookie[0], 2);
        $cookie = explode(';', $explodeCookie[1])[0];
        return [
            'token' => $token,
            'cookies' => CookieJar::fromArray(['PHPSESSID' => $cookie], 'b2b.agrip.pl'),
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
                'Host' => 'b2b.agrip.pl',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36',
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
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36',
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
        return Str::contains($contents, '<i class="icon icon-person"></i>');
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
        $dataRequest = $this->getDataLogin($client);
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
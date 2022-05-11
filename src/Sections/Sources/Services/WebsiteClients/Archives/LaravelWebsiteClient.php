<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use Exception;
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

class LaravelWebsiteClient implements WebsiteClient
{
    use CrawlerHtml;

    /** @var string $login */
    protected $login;

    /** @var string $password */
    protected $password;

    /** @var string $login */
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
        $this->password = $password;
        $this->login2 = $login2;
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
     * @param int $attempts
     * @return string
     * @throws DelivererAgripException
     */
    public function getContents(string $url, array $options = [], string $contentValid = null, int $attempts = 3): string
    {
        $client = $this->getClient();
        DelivererLogger::log(sprintf('Get content %s', $url));
        $response = $client->get($url, $options);
        $content = $response->getBody()->getContents();
       try{
           if ($contentValid) {
               if (!Str::contains($content, $contentValid) && !Str::contains($content, 'PNG') && !Str::contains($content, 'Exif')) {
                   throw new DelivererAgripException('Content is not authorized.');
               }
           } else if (!$this->isLoggedClient($content)) {
               throw new DelivererAgripException('Content is not authorized 2.');
           }
       } catch (Exception $e){
           DelivererLogger::log(sprintf('Content %s', $content));
           if ($attempts){
               $attempts--;
               sleep(20);
               $this->getCookiesLogin(true);
               return $this->getContents($url, $options, $contentValid, $attempts);
           }
           throw $e;
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
     * @param bool $force
     * @return CookieJar
     */
    private function getCookiesLogin(bool $force = false): CookieJar
    {

        $keyCache = sprintf('deliverer-agrip_cookies_login_3_%s', $this->login);
        if ($force){
            Cache::forget($keyCache);
        }
        return Cache::remember($keyCache, 10000, function () {
            $stack = new HandlerStack();
            $stack->setHandler(new CurlHandler());
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
                $size = $request->getBody()->getSize();
                return $request->withHeader('Content-Length', $size);
            })); $client = $this->getClientAnonymous();
            $dataLogin = $this->getDataLogin($client);
            $client = new Client(['verify' => false, 'cookies' => $dataLogin['cookies']]);
            $cook1 = $dataLogin['cookies']->toArray()[0]['Value'];
            $cook2 = $dataLogin['cookies']->toArray()[1]['Value'];
            $options = $this->getDefaultOptionsRequest([
                RequestOptions::FORM_PARAMS => [
                    '_token' => $dataLogin['token'],
                    'contractor_code' => $this->login,
                    'name' => $this->login2,
                    'password' => $this->password,
                ],
                'headers' => [
                    'content-length' => '108',
                    'content-type' => 'application/x-www-form-urlencoded',
                    'cookie' => sprintf('XSRF-TOKEN=%s; laravel_session=%s', $cook1, $cook2), 'origin' => 'https://b2b.agrip.pl',
                ],
                'allow_redirects' => false,
                'handler' => $stack,
            ]);
            $response = $client->post('https://b2b.agrip.pl/login', $options);
            $contents = $response->getBody()->getContents();
            $cookie = $response->getHeader('Set-Cookie');
            $explodeCookie1 = explode('=', $cookie[0], 2);
            $explodeCookie2 = explode('=', $cookie[1], 2);
            $cookie1 = explode(';', $explodeCookie1[1])[0];
            $cookie2 = explode(';', $explodeCookie2[1])[0];
            $cookieResponse = CookieJar::fromArray(['XSRF-TOKEN' => $cookie1, 'laravel_session' => $cookie2], 'b2b.agrip.pl');
            $client = new Client(['verify' => false, 'cookies' => $cookieResponse]);
            $response = $client->get('https://b2b.agrip.pl/start', $this->getDefaultOptionsRequest([
                'headers' => [
                    'cookie' => sprintf('XSRF-TOKEN=%s; laravel_session=%s', $cook1, $cook2), 'origin' => 'https://b2b.agrip.pl',
                ],
            ]));
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
        $response = $client->get('https://b2b.agrip.pl/login', [
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'accept-encoding' => 'gzip, deflate, br',
            'accept-language' => 'pl-PL,pl;q=0.9',
            'sec-ch-ua' => '"Chromium";v="92", " Not A;Brand";v="99", "Google Chrome";v="92"',
            'sec-ch-ua-mobile' => '?0',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'none',
            'sec-fetch-user' => '?1',
            'upgrade-insecure-requests' => '1',
            'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36',
        ]);
        $content = $response->getBody()->getContents();
        $crawler = $this->getCrawler($content);
        $token = $this->getAttributeCrawler($crawler->filter('input[name="_token"]'), 'value');
        if (!$token) {
            throw new DelivererAgripException('Not found token.');
        }
        $cookie = $response->getHeader('Set-Cookie');
        $explodeCookie1 = explode('=', $cookie[0], 2);
        $explodeCookie2 = explode('=', $cookie[1], 2);
        $cookie1 = explode(';', $explodeCookie1[1])[0];
        $cookie2 = explode(';', $explodeCookie2[1])[0];
        return [
            'token' => $token,
            'cookies' => CookieJar::fromArray([
                'XSRF-TOKEN' => $cookie1,
                'laravel_session' => $cookie2
            ], 'b2b.agrip.pl'),
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
        try{
            $response = $client->request($method, $url, $options);
        } catch (Exception $e){
            $code = $e->getCode();
            if ($code === 401){
                $this->getCookiesLogin(true);
                return $this->getContentAjax($url, $options, $method, $contentValid);
            }
        }
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
                'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36',
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
        return Str::contains($contents, '<i class="fa fa-user"></i>');
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
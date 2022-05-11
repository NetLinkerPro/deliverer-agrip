<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class NodeWebsiteClient implements WebsiteClient
{
    use CrawlerHtml;

    /** @var string $login */
    protected $login;

    /** @var string $password */
    protected $password;

    /** @var string $viewState */
    private $viewState;

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
     * @param HandlerStack|null $stack
     * @return Client
     */
    public function getClientAnonymous(?HandlerStack $stack = null): Client
    {
        $config = ['verify' => false, 'cookies' => true];
        if ($stack) {
            $config['handler'] = $stack;
        }
        return new Client($config);
    }

    /**
     * Get contents
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
        $contents = $response->getBody()->getContents();
        if (!$this->isLoggedClient($contents)) {
            throw new DelivererAgripException('Content is not authorized.');
        }
        return $contents;
    }

    /**
     * Get client
     *
     * @param HandlerStack|null $stack
     * @return Client
     */
    private function getClient(HandlerStack $stack = null): Client
    {
        $cookiesLogin = $this->getCookiesLogin();
        $options = $this->getDefaultOptionsRequest(['verify' => false, 'cookies' => $cookiesLogin]);
        if ($stack){
            $options['handler'] = $stack;
        }
        return new Client($options);
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
            $optionsPost = $this->getDefaultOptionsRequest([
                RequestOptions::FORM_PARAMS => [
                    'login_redirect' => 'https://www.agrip.pl/offer/pl/0/',
                    'login_action' => 'login',
                    'login_login' => $this->login,
                    'login_passwd' => $this->password,
                    'accept_cookie' =>'on',
                ],
            ]);
            $response = $client->post('https://www.agrip.pl/offer/pl/0/', $optionsPost);
            $contents = $response->getBody()->getContents();
            if (!$this->isLoggedClient($contents)) {
                throw new DelivererAgripException('Not authorized content for Agrip');
            }
            return $client->getConfig('cookies');
        });
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
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = '[{"type":'): string
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('Content-Length', $request->getBody()->getSize());
        }));
        $client = $this->getClient();
        $options = $this->getDefaultOptionsRequestAjax($options);
        $logSuffix = $options['_log_suffix']??'';
        DelivererLogger::log(sprintf('Get content AJAX %s%s', $url, $logSuffix));
        $response = $client->request($method, $url, $options);
        $contents = $response->getBody()->getContents();
         if (!Str::contains($contents, $contentValid)) {
            throw new DelivererAgripException('Content is not valid.');
        }
        return $contents;
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
                'User-Agent'=>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36',
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
                'Accept-Language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
                'Referer' => 'https://www.agrip.pl/offer/pl/_/',
                'User-Agent'=>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36',
                'X-Requested-With'=>'XMLHttpRequest',
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
        return Str::contains($contents, '<div id="divLogout">');
    }

    /**
     * Get cookie client
     *
     * @param string $name
     * @param Client $client
     * @return string
     */
    private function getCookieClient(string $name, Client $client):?string
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
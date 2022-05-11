<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\ResponseInterface;

class Asp2WebsiteClient implements WebsiteClient
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
        DelivererLogger::log(sprintf('Get content %s', $url));
        $options = $this->getDefaultAjaxRequestOptions($options);
        $response = $client->get($url, $options);
        $content = $response->getBody()->getContents();
        if (!Str::contains($content, '"isSuccess":true,')) {
            throw new DelivererAgripException('Content is not authorized.');
        }
        return $content;
    }

    /**
     * Get content
     *
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     * @throws DelivererAgripException
     */
    public function getResponse(string $url, array $options = []): ResponseInterface
    {
        $client = $this->getClient();
        DelivererLogger::log(sprintf('Get content %s', $url));
        $options = $this->getDefaultAjaxRequestOptions($options);
        return $client->get($url, $options);
    }

    /**
     * Get client
     *
     * @return Client
     */
    private function getClient(): Client
    {
        $token = $this->getToken();
        return new Client(['verify' => false, 'cookies' => true, 'headers' => [
            'authorization' => sprintf('Bearer %s', $token),
        ]
        ]);
    }

    /**
     * Get cookies login
     *
     * @return CookieJar
     */
    private function getCookiesLogin(): CookieJar
    {
        $keyCache = sprintf('deliverer-agrip_cookies_login_%s', $this->login);
        return Cache::remember($keyCache, 3500, function () {
            $client = $this->getClientAnonymous();
//            $requestVerificationToken = $this->getRequestVerificationToken($client);
            $response = $client->post('https://api.agrip.com/api/Authentication/Login', [
                RequestOptions::JSON => [
                    'email' => $this->login,
                    'password' => $this->password,
                ],
            ]);
            $content = $response->getBody()->getContents();
            if (!Str::contains($content, '"customerKey":"EET Pol')) {
                throw new DelivererAgripException('Failed login to Agrip');
            }
            $data = json_decode($content, true, 512);
            if (!$data['isSuccess']) {
                throw new DelivererAgripException('Failed login to Agrip (isSuccess = false).');
            }
            return $data['model']['token'];
        });
    }

    /**
     * Get token
     *
     * @return string
     */
    private function getToken(): string
    {
        $keyCache = sprintf('deliverer-agrip_token_login_%s', $this->login);
        return Cache::remember($keyCache, 3500, function () {
            $client = $this->getClientAnonymous();
//            $requestVerificationToken = $this->getRequestVerificationToken($client);
            $response = $client->post('https://api.agrip.com/api/Authentication/Login', [
                RequestOptions::JSON => [
                    'email' => $this->login,
                    'password' => $this->password,
                ],
            ]);
            $content = $response->getBody()->getContents();
            if (!Str::contains($content, '"customerKey":"EET Pol')) {
                throw new DelivererAgripException('Failed login to Agrip');
            }
            $data = json_decode($content, true, 512);
            if (!$data['isSuccess']) {
                throw new DelivererAgripException('Failed login to Agrip (isSuccess = false).');
            }
            return $data['model']['token'];
        });
    }

    /**
     * Get request verification token
     *
     * @param Client $client
     * @return string
     */
    private function getRequestVerificationToken(Client $client): string
    {
        $response = $client->get('https://b2b.agrip.net.pl/Account/Login');
        $content = $response->getBody()->getContents();
        $crawler = $this->getCrawler($content);
        return $this->getAttributeCrawler($crawler->filter('input[name="__RequestVerificationToken"]'), 'value');
    }

    /**
     * Get content AJAX
     *
     * @param string $url
     * @param array $options
     * @param string $method
     * @return string
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getContentAjax(string $url, array $options = [], string $method = 'POST'): string
    {
        $client = $this->getClient();
        DelivererLogger::log(sprintf('Get content AJAX %s', $url));
        $options = $this->getDefaultAjaxRequestOptions($options);
        $response = $client->request($method, $url, $options);
        $content = $response->getBody()->getContents();
        if (!Str::contains($content, ',"isSuccess":true,')) {
            throw new DelivererAgripException('Content is not authorized.');
        }
        return $content;
    }

    /**
     * Get default AJAX request options
     *
     * @param array $options
     * @return array
     */
    private function getDefaultAjaxRequestOptions(array $options): array
    {
        return array_merge_recursive($options, ['headers' => [
            'x-eet-businessentityid' => '15',
            'x-eet-culture' => 'pl-pl',
            'x-eet-marketid' => '1002',
        ]]);
    }
}
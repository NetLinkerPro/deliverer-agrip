<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\ResponseInterface;

class Prestashop2WebsiteClient implements WebsiteClient
{
    use CrawlerHtml;

    const AUTHORIZED_CONTENTS='title="Wyświetl moje konto klienta" rel="nofollow" class="header-btn header-user-btn"';

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
     * @param int $attempts
     * @return string
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getContents(string $url, array $options = [], int $attempts = 2): string
    {
        DelivererLogger::log(sprintf('Get content %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $method = $options['_']['method'] ?? 'get';
        $response = $client->request($method, $url, $options);
        $contents = $response->getBody()->getContents();
        if (!Str::contains($contents, self::AUTHORIZED_CONTENTS)) {
            if ($attempts > 1) {
                $attempts -= 1;
                sleep(5);
                $this->getCookiesLogin(true);
                return $this->getContents($url, $options, $attempts);
            } else {
                throw new DelivererAgripException('Content is not authorized.');
            }
        }
        return $contents;
    }

    /**
     * Get client
     *
     * @param bool $forceLogin
     * @return Client
     */
    private function getClient(bool $forceLogin = false): Client
    {
        $cookiesLogin = $this->getCookiesLogin($forceLogin);
        return new Client(['verify' => false, 'cookies' => $cookiesLogin]);
    }

    /**
     * Get cookies login
     *
     * @param bool $forceLogin
     * @return CookieJar
     */
    private function getCookiesLogin(bool $forceLogin = false): CookieJar
    {
        $keyCache = sprintf('deliverer-agrip_cookies_login_%s', $this->login);
        if ($forceLogin) {
            Cache::forget($keyCache);
        }
        return Cache::remember($keyCache, 3600, function () {
            $client = $this->getClientAnonymous();
            $response = $client->post('https://b2b.agrip.pl/logowanie?back=my-account', [
                RequestOptions::FORM_PARAMS => [
                    'back' => 'my-account',
                    'email' => $this->login,
                    'password' => $this->password,
                    'submitLogin' => 1,
                ],
            ]);
            $content = $response->getBody()->getContents();
            if (!Str::contains($content, self::AUTHORIZED_CONTENTS)) {
                throw new DelivererAgripException('Failed login to Agrip');
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
     * @param int $attempts
     * @return string
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = 'data-button-action=\"add-to-cart\"', int $attempts = 2): string
    {
        DelivererLogger::log(sprintf('Get content AJAX %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $options['headers']['accept'] = 'application/json, text/javascript, */*; q=0.01';
        $options['headers']['accept-language'] = 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7';
        $options['headers']['user-agent'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.54 Safari/537.36';
        $options['headers']['X-Requested-With'] = 'XMLHttpRequest';
        $response = $client->request($method, $url, $options);
        $content = $response->getBody()->getContents();
        if (!Str::contains($content, $contentValid)) {
            if ($attempts > 1) {
                $attempts -= 1;
                sleep(5);
                $this->getCookiesLogin(true);
                return $this->getContentAjax($url, $options, $method, $contentValid, $attempts);
            } else {
                throw new DelivererAgripException('Content is not authorized.');
            }
        }
        return $content;
    }
}
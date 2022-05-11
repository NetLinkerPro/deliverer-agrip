<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;

use Exception;
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
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FixJson;
use Psr\Http\Message\ResponseInterface;

class Ekspert2WebsiteClient implements WebsiteClient
{
    use CrawlerHtml, FixJson;

    /** @var string $login */
    protected $login;

    /** @var string $password */
    protected $password;

    /** @var array|null $lastDataAspx */
    protected $lastDataAspx;

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
        return $client->get($url, $this->getDefaultOptions($options));
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
     * @throws DelivererAgripException|GuzzleException
     */
    public function getContents(string $url, array $options = [], int $attempt = 2, string $contentValid = '<a id="menu-accountButton" href="/account/logout"'): string
    {
        DelivererLogger::log(sprintf('Get content %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $method = $options['_']['method'] ?? 'get';
        $options['timeout'] = 25;
        $options['connect_timeout'] = 25;
        try{
            $response = $client->request($method, $url, $this->getDefaultOptions($options));
        }catch (Exception $e){
            if ($attempt > 1){
                $options['_']['force_login'] = true;
                $attempt -=1;
                return $this->getContents($url, $options, $attempt);
            } else {
                throw $e;
            }
        }
        $contents = $response->getBody()->getContents();
        if (!Str::contains($contents, $contentValid)) {
            if ($attempt > 1) {
                $attempt -= 1;
                $options['_']['force_login'] = true;
                return $this->getContents($url, $options, $attempt);
            }
            throw new DelivererAgripException('Content is not authorized.');
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
     * Get default options
     *
     * @param array $options
     * @return array
     */
    private function getDefaultOptions(array $options): array
    {
        return array_merge_recursive([
            'headers' => [
                'Accept-Language' =>'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
                'User-Agent'=>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36',
            ]
        ], $options);
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
            $response = $client->post('https://b2b.ama-europe.pl/?continue=https://b2b.ama-europe.pl/start', $this->getDefaultOptions([
                RequestOptions::FORM_PARAMS => [
                    'continue' => 'https://b2b.ama-europe.pl/start',
                    'user' => $this->login,
                    'password' => $this->password,
                    'login' => 'Zaloguj',
                ],
            ]));
            $contents = $response->getBody()->getContents();
            if (!Str::contains($contents, '<a id="menu-accountButton" href="/account/logout"')) {
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
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = '<ul class="jqueryFileTree"', int $attempts = 2): string
    {
        DelivererLogger::log(sprintf('Get AJAX content %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $options = $this->getDefaultOptions($options);
        $options['headers']['X-Requested-With'] = 'XMLHttpRequest';
        $response = $client->request($method, $url, $options);
        $contents = $response->getBody()->getContents();
        if (!Str::contains($contents, $contentValid)) {
            if ($attempts > 1) {
                $attempts -= 1;
                sleep(10);
                return $this->getContents($url, $options, $method, $contentValid, $attempts);
            }
            throw new DelivererAgripException('Content is not authorized.');
        }
        return $contents;
    }


    /**
     * Get data Aspx
     *
     * @param string $content
     * @return array
     */
    public function getDataAspx(string $content): array
    {
        $crawler = $this->getCrawler($content);
        $eventTarget = $this->getAttributeCrawler($crawler->filter('input[name="__EVENTTARGET"]'), 'value');
        $eventArgument = $this->getAttributeCrawler($crawler->filter('input[name="__EVENTARGUMENT"]'), 'value');
        $viewState = $this->getAttributeCrawler($crawler->filter('input[name="__VIEWSTATE"]'), 'value');
        $viewStateGenerator = $this->getAttributeCrawler($crawler->filter('input[name="__VIEWSTATEGENERATOR"]'), 'value');
        $eventValidation = $this->getAttributeCrawler($crawler->filter('input[name="__EVENTVALIDATION"]'), 'value');
        $previousPage = $this->getAttributeCrawler($crawler->filter('input[name="__PREVIOUSPAGE"]'), 'value');
        return [
            'event_target' => $eventTarget ?? '',
            'event_argument' => $eventArgument ?? '',
            'view_state' => $viewState ?? '',
            'view_state_generator' => $viewStateGenerator ?? '',
            'event_validation' => $eventValidation ?? '',
            'previous_page' => $previousPage,
        ];
    }
}
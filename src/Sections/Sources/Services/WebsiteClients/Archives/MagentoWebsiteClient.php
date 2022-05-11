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

class MagentoWebsiteClient implements WebsiteClient
{
    use CrawlerHtml;

    /** @var string $login */
    protected $login;

    /** @var string $password */
    protected $password;

    /** @var string|null $lastViewstateKey */
    protected $lastViewstateKey;

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
        $contents = $response->getBody()->getContents();
//        $dataAspx = $this->getDataAspx($contents);
//        $this->lastViewstateKey = $dataAspx['view_state_key'];
        return $contents;
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
        if (!Str::contains($contents, '<span class="contact-title">Moje konto</span>')) {
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
            $formKey = $this->getFormKey($client);
            $response = $client->post('https://www.simple24.pl/customer/account/loginPost/', [
                RequestOptions::FORM_PARAMS => [
                    'form_key' => $formKey,
                    'test' => $this->login,
                    'login[username]' => $this->login,
                    'login[password]' => $this->password,
                    'persistent_remember_me' => 'on',
                    'send' => '',
                ],
            ]);
            $content = $response->getBody()->getContents();
            if (!Str::contains($content, '<span class="contact-title">Moje konto</span>')) {
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
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = "<span class='pozycjaKategoriiWielo'", int $attempts = 2): string
    {
        throw new DelivererAgripException('Not implemented.');
        DelivererLogger::log(sprintf('Get content AJAX %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
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

    /**
     * Get lastViewstateKey
     *
     * @return string|null
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getLastViewstateKey(): ?string
    {
        if (!$this->lastViewstateKey) {
            $contents = $this->getContents('https://www.hurt.aw-narzedzia.com.pl/ProduktyWyszukiwanie.aspx?search=');
            $dataAspx = $this->getDataAspx($contents);
            $this->lastViewstateKey = $dataAspx['view_state_key'];
        }
        return $this->lastViewstateKey;
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
        $viewStateKey = $this->getAttributeCrawler($crawler->filter('input[name="__VIEWSTATE_KEY"]'), 'value');
        $viewStateGenerator = $this->getAttributeCrawler($crawler->filter('input[name="__VIEWSTATEGENERATOR"]'), 'value');
        $eventValidation = $this->getAttributeCrawler($crawler->filter('input[name="__EVENTVALIDATION"]'), 'value');
        Log::debug(sprintf('get %s', $viewStateKey));
        return [
            'event_target' => $eventTarget ?? '',
            'event_argument' => $eventArgument ?? '',
            'view_state' => $viewState ?? '',
            'view_state_key' => $viewStateKey,
            'view_state_generator' => $viewStateGenerator ?? '',
            'event_validation' => $eventValidation ?? '',
        ];
    }

    /**
     * Get form key
     *
     * @param Client $client
     * @return string
     */
    private function getFormKey(Client $client): string
    {
        $response = $client->get('https://www.simple24.pl/customer/account/login/');
        $contents = $response->getBody()->getContents();
        $crawler = $this->getCrawler($contents);
        return $this->getAttributeCrawler($crawler->filter('input[name="form_key"]'), 'value');
    }
}
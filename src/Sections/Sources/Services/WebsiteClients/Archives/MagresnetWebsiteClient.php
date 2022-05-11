<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
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
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FixJson;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MagresnetWebsiteClient implements WebsiteClient
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
        $contents = $response->getBody()->getContents();
        $this->lastDataAspx = $this->getDataAspx($contents);
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
        return new Client(['verify' => false, 'cookies' => true, 'timeout' => 30, 'connect_timeout' => 30]);
    }

    /**
     * Get content
     *
     * @param string $url
     * @param array $options
     * @return string
     * @throws DelivererAgripException|GuzzleException
     */
    public function getContents(string $url, array $options = [], int $attempt = 2): string
    {
        DelivererLogger::log(sprintf('Get content %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $method = $options['_']['method'] ?? 'get';
        $response = $client->request($method, $url, $options);
        $contents = $response->getBody()->getContents();
        if (!Str::contains($contents, 'ctl00$ContentPlaceHolder1$btnProducent')) {
            if ($attempt > 1) {
                $attempt -= 1;
                $options['_']['force_login'] = true;
                return $this->getContents($url, $options, $attempt);
            }
            throw new DelivererAgripException('Content is not authorized.');
        }
        $this->lastDataAspx = $this->getDataAspx($contents);
        return $contents;
    }

    /**
     * Get client
     *
     * @param bool $forceLogin
     * @param null $stack
     * @return Client
     */
    private function getClient(bool $forceLogin = false, $stack = null): Client
    {
        $cookiesLogin = $this->getCookiesLogin($forceLogin);
        $config = ['verify' => false, 'cookies' => $cookiesLogin, 'timeout' => 30, 'connect_timeout' => 30];
        if ($stack) {
            $config['handler'] = $stack;
        }
        return new Client($config);
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
            $content = $client->get('http://212.180.197.238/Default.aspx')->getBody()->getContents();
            $dataAspxSite = $this->getDataAspx($content);
            $response = $client->post('http://212.180.197.238/Default.aspx', [
                RequestOptions::FORM_PARAMS => [
                    '__LASTFOCUS' => '',
                    '__EVENTTARGET' => $dataAspxSite['event_target'],
                    '__EVENTARGUMENT' => $dataAspxSite['event_argument'],
                    '__VIEWSTATE' => $dataAspxSite['view_state'],
                    '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                    '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                    'Login1$UserName' => $this->login,
                    'Login1$Password' => $this->password,
                    'Login1$LoginButton' => 'Zaloguj',
                ],
            ]);
            $contents = $response->getBody()->getContents();
            if (!Str::contains($contents, 'ctl00$ContentPlaceHolder1$btnProducent')) {
                throw new DelivererAgripException('Failed login to Agrip');
            }
            $this->lastDataAspx = $this->getDataAspx($contents);
            return $client->getConfig('cookies');
        });
    }

    /**
     * Get last data ASPX
     *
     * @return array|null
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function getLastDataAspx(): ?array
    {
        if (!$this->lastDataAspx) {
            $contents = $this->getContents('http://212.180.197.238/OfertaMobile.aspx');
            $this->lastDataAspx = $this->getDataAspx($contents);
        }
        return $this->lastDataAspx;
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
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = "ctl00_ContentPlaceHolder1_UpdatePanel1", int $attempts = 2): string
    {
        DelivererLogger::log(sprintf('Get AJAX content %s', $url));
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('Content-Length', $request->getBody()->getSize());
        }));
        $client = $this->getClient($options['_']['force_login'] ?? false,$stack);
        $options = array_merge_recursive($options, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36',
                'X-MicrosoftAjax' => 'Delta=true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' =>'application/x-www-form-urlencoded; charset=UTF-8',
                'Cookie' =>sprintf('ASP.NET_SessionId=%s', $this->getCookieClient('ASP.NET_SessionId', $client)),
            ]
        ]);
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
        $oldDataAspx = $options['_']['old_data_aspx'] ?? false;
        if (!$oldDataAspx){
            $this->lastDataAspx = $this->getDataAspxFromAjax($contents);
        }
        $html = explode('_UpdatePanel1|', $contents)[1];
        $html = explode('|0|hiddenField|__EVENTTARGET|', $html)[0];
        $crawler = $this->getCrawler($html);
        return $crawler->html();
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
        $viewStateEncrypted = $this->getAttributeCrawler($crawler->filter('input[name="__VIEWSTATEENCRYPTED"]'), 'value');
        $asyncPost = $this->getAttributeCrawler($crawler->filter('input[name="__ASYNCPOST"]'), 'value');
        return [
            'event_target' => $eventTarget ?? '',
            'event_argument' => $eventArgument ?? '',
            'view_state' => $viewState ?? '',
            'view_state_generator' => $viewStateGenerator ?? '',
            'event_validation' => $eventValidation ?? '',
            'previous_page' => $previousPage ?? '',
            'view_state_encrypted' => $viewStateEncrypted ?? '',
            'async_post' => $asyncPost ?? '',
        ];
    }

    /**
     * Get cookie client
     *
     * @param string $cookieName
     * @param Client $client
     * @return string
     * @throws DelivererAgripException
     */
    private function getCookieClient(string $cookieName, Client $client): string
    {
        $cookies = $client->getConfig('cookies');
        /** @var CookieJar $cookie */
        foreach ($cookies as $cookie){
            if ($cookieName === $cookie->getName()){
                return $cookie->getValue();
            }
        }
        throw new DelivererAgripException('Not found cookie.');
    }

    private function getDataAspxFromAjax(string $contents)
    {
        $eventTarget = $this->getHiddenField('__EVENTTARGET', $contents);
        $eventArgument =$this->getHiddenField('__EVENTARGUMENT', $contents);
        $viewState =  $this->getHiddenField('__VIEWSTATE', $contents);
        $viewStateGenerator = $this->getHiddenField('__VIEWSTATEGENERATOR', $contents);
        $eventValidation =$this->getHiddenField('__EVENTVALIDATION', $contents);
        $previousPage =$this->getHiddenField('__PREVIOUSPAGE', $contents);
        $viewStateEncrypted = $this->getHiddenField('__VIEWSTATEENCRYPTED', $contents);
        return [
            'event_target' => $eventTarget ?? '',
            'event_argument' => $eventArgument ?? '',
            'view_state' => $viewState ?? '',
            'view_state_generator' => $viewStateGenerator ?? '',
            'event_validation' => $eventValidation ?? '',
            'previous_page' => $previousPage ?? '',
            'view_state_encrypted' => $viewStateEncrypted ?? '',
            'async_post' => $asyncPost ?? '',
        ];
    }

    /**
     * Get hidden field
     *
     * @param string $key
     * @param string $contents
     * @return string
     */
    private function getHiddenField(string $key, string $contents): string
    {
        $key = sprintf('hiddenField|%s|', $key);
        $text = explode($key, $contents)[1] ?? '';
        return explode('|', $text)[0];
    }
}
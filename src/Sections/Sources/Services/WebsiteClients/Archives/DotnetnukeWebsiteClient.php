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
use Psr\Http\Message\ResponseInterface;

class DotnetnukeWebsiteClient implements WebsiteClient
{
    const VALID_CONTENT_ANONYMOUS = 'id="dnn_dnnLOGIN_cmdLogin"';
    const VALID_CONTENT_LOGGED = 'id="dnn_dnnUSER_cmdRegister"';

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
     * @throws DelivererAgripException|GuzzleException
     */
    public function getContents(string $url, array $options = []): string
    {
        DelivererLogger::log(sprintf('Get content %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $method = $options['_']['method'] ?? 'get';
        $response = $client->request($method, $url, $options);
        $content = $response->getBody()->getContents();
        if (!Str::contains($content, DotnetnukeWebsiteClient::VALID_CONTENT_LOGGED)) {
            throw new DelivererAgripException('Content is not authorized.');
        }
        return $content;
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
        if ($forceLogin){
            Cache::forget($keyCache);
        }
        return Cache::remember($keyCache, 3600, function () {
            $client = $this->getClientAnonymous();
            $content = $client->get('https://www.agrip-b2b.com.pl/Loguj.aspx')->getBody()->getContents();
            $dataAspxSite = $this->getDataAspx($content);
            $response = $client->post('https://www.agrip-b2b.com.pl/Loguj.aspx', [
                RequestOptions::FORM_PARAMS => [
                    '__EVENTTARGET' => $dataAspxSite['event_target'],
                    '__EVENTARGUMENT' => $dataAspxSite['event_argument'],
                    '__VIEWSTATE' => $dataAspxSite['view_state'],
                    '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                    'ctl00$cph_top$Logowanie$Login1$UserName' => $this->login,
                    'ctl00$cph_top$Logowanie$Login1$Password' => $this->password,
                    'ctl00$cph_top$Logowanie$Login1$LoginButton' => 'zaloguj',
                    'ctl00$hdnZwin' => 'zwiń',
                    'ctl00$hdnRozwin' => 'rozwiń'
                ],
            ]);
            $content = $response->getBody()->getContents();
            if (!Str::contains($content, '<div id="zalogowany" class="login">')) {
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
     * @return string
     * @throws Exception
     */
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = '<a href="/goto'): string
    {
        throw new Exception('Not implemented.');
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

        return [
            'event_target' => $eventTarget ?? '',
            'event_argument' => $eventArgument ?? '',
            'view_state' => $viewState ?? '',
            'view_state_generator' => $viewStateGenerator ?? '',
            'event_validation' =>$eventValidation ?? '',
        ];
    }
}
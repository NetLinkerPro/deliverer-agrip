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

class SagitumWebsiteClient implements WebsiteClient
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
        $contents =$response->getBody()->getContents();
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
    public function getContents(string $url, array $options = [], int $attempt = 2): string
    {
        DelivererLogger::log(sprintf('Get content %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $method = $options['_']['method'] ?? 'get';
        $response = $client->request($method, $url, $options);
        $contents = $response->getBody()->getContents();
        if (!Str::contains($contents, '<a id="cmdWyloguj" class="wyloguj" href="javascript')) {
            if ($attempt > 1){
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
            $content = $client->get('https://b2b.agrip.pl/Forms/Login.aspx')->getBody()->getContents();
            $dataAspxSite = $this->getDataAspx($content);
            $response = $client->post('https://b2b.agrip.pl/Forms/Login.aspx', [
                RequestOptions::FORM_PARAMS => [
                    '__EVENTTARGET' => $dataAspxSite['event_target'],
                    '__EVENTARGUMENT' => $dataAspxSite['event_argument'],
                    '__VIEWSTATE' => $dataAspxSite['view_state'],
                    '__VIEWSTATEGENERATOR' => $dataAspxSite['view_state_generator'],
                    '__EVENTVALIDATION' => $dataAspxSite['event_validation'],
                    'ctl00$ContentPlaceHolder1$txtLogin$State' => '{&quot;rawValue&quot;:&quot;JacMys&quot;,&quot;validationState&quot;:&quot;&quot;}',
                    'ctl00$ContentPlaceHolder1$txtLogin' => $this->login,
                    'ctl00$ContentPlaceHolder1$txtPassword$State' => '{&quot;rawValue&quot;:&quot;3369&quot;,&quot;validationState&quot;:&quot;&quot;}',
                    'ctl00$ContentPlaceHolder1$txtPassword' => $this->password,
                    'ctl00$ContentPlaceHolder1$btnLogin' => 'Zaloguj się',
                    'DXScript' => '1_16,1_66,1_17,1_18,1_19,1_225,1_226,1_28,1_20,1_224',
                    'DXCss' => '1_69,1_70,1_71,1_250,1_247,1_251,1_248,../Content/Style.css',
                ],
            ]);
            $contents = $response->getBody()->getContents();
            if (!Str::contains($contents, '<a id="cmdWyloguj" class="wyloguj" href="javascript')) {
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
        if (!$this->lastDataAspx){
            $contents = $this->getContents('https://b2b.agrip.pl/Forms/ArticleGroups.aspx');
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
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = "{'result':{'html':", int $attempts = 2): string
    {
        DelivererLogger::log(sprintf('Get AJAX content %s', $url));
        $client = $this->getClient($options['_']['force_login'] ?? false);
        $response = $client->request($method, $url, $options);
        $contents = $response->getBody()->getContents();
        if (!Str::contains($contents, $contentValid)) {
            if ($attempts > 1){
                $attempts -= 1;
                sleep(10);
                return $this->getContents($url, $options, $method, $contentValid, $attempts);
            }
            throw new DelivererAgripException('Content is not authorized.');
        }
        $eventValidation = explode('/*DX*/(', $contents)[0];
        $eventValidation = explode('|', $eventValidation, 2)[1];
        $this->lastDataAspx['event_validation'] = $eventValidation;
        $contents = explode('/*DX*/(', $contents)[1];
        $contents = Str::replaceLast(')', '', $contents);
        $contents = str_replace('-\-', '--', $contents);
        $contents = $this->fixJSON($contents);
        $dataJson = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
        $html = $dataJson['result']['html'];
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
        return [
            'event_target' => $eventTarget ?? '',
            'event_argument' => $eventArgument ?? '',
            'view_state' => $viewState ?? '',
            'view_state_generator' => $viewStateGenerator ?? '',
            'event_validation' => $eventValidation ?? '',
            'previous_page' =>$previousPage,
        ];
    }
}
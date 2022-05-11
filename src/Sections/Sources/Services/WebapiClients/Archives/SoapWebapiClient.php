<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives;


use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Contracts\WebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class SoapWebapiClient implements WebapiClient
{
    use CrawlerHtml;

    /** @var string $token */
    protected $token;

    /** @var string $login */
    protected $login;

    /** @var string $password */
    protected $password;

    /**
     * SoapApiClient constructor
     *
     * @param string $token
     * @param string $login
     * @param string $password
     */
    public function __construct(string $token, string $login, string $password)
    {
        $this->token = $token;
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Send request
     *
     * @param string $body
     * @return string
     * @throws DelivererAgripException
     */
    public function request(string $body): string
    {
        $client = $this->getClient();
        $response = $client->post('https://webapi.agrip.pl/WebAPI.asmx', [
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8'
            ],
            'body'   => $body
        ]);
        $content = $response->getBody()->getContents();
        $this->checkErrorResponse($content);
        return $content;
    }

    /**
     * Get session key
     *
     * @return string
     */
    public function getSessionKey(): string
    {
        $keyCache = sprintf('deliverer-agrip_session_key_%s_%s', $this->token, $this->login);
        return Cache::remember($keyCache, 3500, function(){
            $bodyXmlLogin = $this->getBodyXmlLogin();
            $contentXmlResponse = $this->request($bodyXmlLogin);
            $crawler = $this->getCrawler($contentXmlResponse);
            $sessionKey = $crawler->filter('SessionKey')->text();
            if (!$sessionKey){
                throw new DelivererAgripException('Failed get session key.');
            }
            return $sessionKey;
        });
    }

    /**
     * Get body XML login
     *
     * @return string
     */
    private function getBodyXmlLogin():string{
        return sprintf('<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <doLogin xmlns="http://webapi.agrip.pl/">
                  <Token>%s</Token>
                  <EMail>%s</EMail>
                  <Passwd>%s</Passwd>
                  <RefreshPrice>true</RefreshPrice>
                </doLogin>
              </soap:Body>
            </soap:Envelope>', $this->token, $this->login, $this->password);
    }

    /**
     * Get client
     *
     * @return Client
     */
    private function getClient(): Client
    {
        return new Client(['verify' =>false]);
    }

    /**
     * Check error response
     *
     * @param string $content
     * @throws DelivererAgripException
     */
    private function checkErrorResponse(string $content): void
    {
        if (!Str::contains($content, '<ErrorCode>Ok</ErrorCode>')){
            throw new DelivererAgripException($content);
        }
    }
}
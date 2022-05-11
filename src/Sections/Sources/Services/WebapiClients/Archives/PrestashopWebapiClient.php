<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives;


use Exception;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Contracts\WebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\KindPrestaShop\PrestaShopWebService;

class PrestashopWebapiClient extends PrestaShopWebService implements WebapiClient
{
    use CrawlerHtml;

    /** @var string $urlApi */
    protected $urlApi;

    /** @var string $apiKey */
    protected $apiKey;

    /**
     * SoapApiClient constructor
     *
     * @param string $urlApi
     * @param string $apiKey
     * @param bool $debug
     */
    public function __construct(string $urlApi, string $apiKey, bool $debug = false)
    {
        parent::__construct($urlApi, $apiKey, $debug);
        $this->urlApi = $urlApi;
        $this->apiKey = $apiKey;
    }

    /**
     * Send request
     *
     * @param string $body
     * @return string
     * @throws Exception
     */
    public function request(string $body): string
    {
       throw new Exception('Not implemented');
    }

    /**
     * Get session key
     *
     * @return string
     * @throws Exception
     */
    public function getSessionKey(): string
    {
        throw new Exception('Not implemented');
    }
}
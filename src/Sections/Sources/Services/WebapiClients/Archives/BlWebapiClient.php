<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives;


use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Contracts\WebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class BlWebapiClient implements WebapiClient
{

    /** @var string $token */
    protected $token;

    /**
     * SoapApiClient constructor
     *
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->token = $token;
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
        throw new DelivererAgripException('Not implemented. Use "sendRequest" method.');
    }

    /**
     * Send request
     *
     * @param string $method
     * @param array $parameters
     * @return array
     * @throws DelivererAgripException
     */
    public function sendRequest(string $method, array $parameters): array
    {
        $client = $this->getClient();
        $this->waitRateLimit();
        $response = $client->post('https://api.baselinker.com/connector.php', [
            RequestOptions::FORM_PARAMS => [
                'token' => $this->token,
                'method' => $method,
                'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            ],
            'timeout' => 120,
            'connect_timeout' => 120,
        ]);
        $contents = $response->getBody()->getContents();
        $data = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
        $errorMessage = $data['error_message'] ?? null;
        if ($errorMessage) {
            throw new DelivererAgripException($errorMessage);
        }
        return $data;
    }

    /**
     * Get client
     *
     * @return Client
     */
    private function getClient(): Client
    {
        return new Client(['verify' => false]);
    }

    /**
     * Get session key
     *
     * @return string
     * @throws DelivererAgripException
     */
    public function getSessionKey(): string
    {
        throw new DelivererAgripException('Not implemented.');
    }

    /**
     * Wait rate limit
     *
     * @throws DelivererAgripException
     */
    private function waitRateLimit(): void
    {
        foreach (range(1, 1000) as $item) {
            $dateFormat = substr(now()->format('YmdHis'), 0, -1);
            $attemptKey = sprintf('%s_%s_%s', get_class($this), $this->token, $dateFormat);
            $attempts = Cache::increment($attemptKey);
            if ($attempts < 7) {
                return;
            }
            DelivererLogger::log('Wait rate limit.');
            sleep(1);
        }
        throw new DelivererAgripException('Wait rate limit time out.');
    }
}
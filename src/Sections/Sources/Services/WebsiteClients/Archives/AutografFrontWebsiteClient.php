<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Psr\Http\Message\ResponseInterface;

class AutografFrontWebsiteClient implements WebsiteClient
{
    use CrawlerHtml;

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
        $options = $this->getDefaultOptionsRequest($options);
        $client = $this->getClientAnonymous();
        DelivererLogger::log(sprintf('Get response anonymous %s', $url));
        return $client->get($url, $options);
    }

    /**
     * Get client anonymous
     *
     * @param HandlerStack|null $stack
     * @return Client
     */
    public function getClientAnonymous(?HandlerStack $stack = null): Client
    {
        $config = ['verify' => false, 'cookies' => true];
        if ($stack){
            $config['handler'] = $stack;
        }
        return new Client($config);
    }

    /**
     * Get content
     *
     * @param string $url
     * @param array $options
     * @return string
     * @throws Exception
     */
    public function getContents(string $url, array $options = []): string
    {
        throw new Exception('Not implemented.');
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
    public function getContentAjax(string $url, array $options = [], string $method = 'POST', string $contentValid = '{"template":"'): string
    {
        throw new Exception('Not implemented');
    }

    /**
     * Get default options request
     *
     * @param array $options
     * @return array
     */
    private function getDefaultOptionsRequest(array $options = []): array
    {
        return array_merge_recursive([
            'headers' => [],
        ], $options);
    }
}
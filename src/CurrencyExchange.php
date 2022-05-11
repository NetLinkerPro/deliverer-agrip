<?php


namespace NetLinker\DelivererAgrip;


use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class CurrencyExchange
{
    /**
     * Get price
     *
     * @param float $price
     * @param string $currencyFrom
     * @param string $currencyTo
     * @return float
     */
    public function getPrice(float $price, string $currencyFrom, string $currencyTo): float
    {
        $exchangeRate = $this->getExchangeRate($currencyFrom, $currencyTo);
        $price = $price * $exchangeRate;
        return round($price, 4);
    }

    /**
     * Get exchange rate
     *
     * @param string $currencyFrom
     * @param string $currencyTo
     * @return float
     */
    private function getExchangeRate(string $currencyFrom, string $currencyTo): float
    {
        $keyCache = sprintf('%s_%s_%s', get_class($this), $currencyFrom, $currencyTo);
        return Cache::remember($keyCache, 36000, function() use (&$currencyFrom, &$currencyTo){
            $url = sprintf('https://api.exchangerate.host/latest?base=%s', $currencyFrom);
            $client = $this->getClient();
            $content = $client->get($url)->getBody()->getContents();
            $data = json_decode($content, true);
            $exchangeRate = $data['rates'][mb_strtoupper($currencyTo)];
            return (float) $exchangeRate;
        });
    }

    /**
     * Get client
     *
     * @return Client
     */
    private function getClient(): Client{
        return new Client(['verify'=>false]);
    }
}
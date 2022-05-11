<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\CsvFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives\SoapListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts\WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Archives\Dedicated1WebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class SoapListProducts implements ListProducts
{
    use CrawlerHtml;

    /** @var SoapWebapiClient $webapiClient */
    protected $webapiClient;

    /** @var WebsiteClient $websiteClient */
    protected $websiteClient;

    /** @var array $idCodeProductMap */
    protected $idCodeProductMap;

    /**
     * SoapListProducts constructor
     *
     * @param string $token
     * @param string $login
     * @param string $password
     */
    public function __construct(string $token, string $login, string $password)
    {
        $this->webapiClient = app(SoapWebapiClient::class, [
            'token' => $token,
            'login' => $login,
            'password' => $password,
        ]);
        $this->websiteClient = app(Dedicated1WebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
    }

    /**
     * Get
     *
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get(?CategorySource $category = null): Generator
    {
        $csvFileReader = $this->getCsvFileReader();
        $this->initializeIdCodeProductMap();
        $products = [];
        $csvFileReader->eachRow(function(array $row) use (&$products){
            $product = $this->getProduct($row);
            if ($product){
                array_push($products, $product);
            }
        });
        foreach ($products as $product){
            yield $product;
        }
    }

    /**
     * Get body XML product list
     *
     * @return string
     */
    private function getBodyXmlProductList(): string
    {
        $sessionKey = $this->webapiClient->getSessionKey();
        return sprintf('<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <getProductList xmlns="http://webapi.agrip.pl/">
                  <SessionKey>%s</SessionKey>
                  <Language>pl-PL</Language>
                  <Currency>PLN</Currency>
                  <CategoryId>0</CategoryId>
                </getProductList>
              </soap:Body>
            </soap:Envelope>', $sessionKey);
    }

    /**
     * Initialize ID code product map
     */
    private function initializeIdCodeProductMap(): void
    {
        if ($this->idCodeProductMap){
            return;
        }
        $keyCache = 'deliverer-agrip_id_code_product_map';
        $this->idCodeProductMap = Cache::remember($keyCache, 3600, function () {
            DelivererLogger::log('Get product list');
            $body = $this->getBodyXmlProductList();
            $contentXmlResponse = $this->webapiClient->request($body);
            $crawler = $this->getCrawler($contentXmlResponse);
            $crawlerProducts = $crawler->filter('rowProductList');
            $idCodeProductMap = [];
            foreach ($crawlerProducts as $contentProduct) {
                $crawlerProduct = new Crawler($contentProduct);
                $id = $this->getTextCrawler($crawlerProduct->filter('id'));
                $code = $this->getTextCrawler($crawlerProduct->filter('code'));
                if ($id && $code) {
                    $idCodeProductMap[$code] = $id;
                }
            }
            return $idCodeProductMap;
        });
    }

    /**
     * Get product
     *
     * @param array $row
     * @return ProductSource|null
     */
    private function getProduct(array $row): ?ProductSource
    {
        $sku = $row['﻿"IndeksKatalogowy"'];
        $id = $this->idCodeProductMap[$sku] ?? null;
        if (!$id){
            DelivererLogger::log(sprintf('Not found ID product for SKU %s', $sku));
            return null;
        }
        $url = sprintf('https://sklep.agrip.pl/pl-pl/produkt/-/%s/-', $id);
        $product = new ProductSource($id, $url);
        $nettoPrice = (float) $row['CenaNetto'];
        $tax = (int) $row['StawkaVAT'];
        $stock = (int) $row['IloscDostepna'];
        $availability = 1;
        $product->setProperty('Kategoria', $row['Kategoria']);
        $product->setPrice($nettoPrice);
        $product->setTax($tax);
        $product->setStock($stock);
        $product->setAvailability($availability);
        return $product;
    }

    /**
     * Get CSV file reader
     *
     * @return CsvFileReader
     * @throws DelivererAgripException
     */
    private function getCsvFileReader(): CsvFileReader
    {
        $urlCsv = $this->getUrlCsv();
       $csvFileReader = app(CsvFileReader::class, ['uri' => $urlCsv]);
        $csvFileReader->setTtlCache(3600);
        $csvFileReader->setDownloadBefore(true);
        return $csvFileReader;
    }

    /**
     * Get URL CSV
     *
     * @return string
     * @throws DelivererAgripException
     */
    private function getUrlCsv(): string
    {
        $content = $this->websiteClient->getContents('https://sklep.agrip.pl/pl-pl/integracja/csv');
        $crawler = $this->getCrawler($content);
        $urlCsv = '';
        $crawler->filter('table.integrtionCSVList tbody tr')
            ->each(function(Crawler $tr) use (&$urlCsv){
            $htmlTr = $tr->html();
            if (Str::contains($htmlTr, '<strong>Netlinker</strong>')){
                $href = $this->getAttributeCrawler($tr->filter('a[data-tag="integration_getCSV"]'), 'href');
                $urlCsv = sprintf('https://sklep.agrip.pl%s', $href);
            }
        });
        if (!$urlCsv){
            throw new DelivererAgripException('Not found URL CSV in website.');
        }
        return $urlCsv;
    }
}
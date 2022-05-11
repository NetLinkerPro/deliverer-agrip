<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Archives;

use Generator;
use Illuminate\Support\Facades\Cache;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Archives\SoapWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use Symfony\Component\DomCrawler\Crawler;

class SoapListCategories implements ListCategories
{
    use CrawlerHtml;

    /** @var SoapWebapiClient $webapiClient */
    protected $webapiClient;

    /**
     * SoapListCategories constructor
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
    }

    /**
     * Get
     *
     * @return Generator|CategorySource[]|array
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     */
    public function get(): Generator
    {
       $categories = $this->getCategories();

        throw new DelivererAgripException('Not implemented');
    }

    /**
     * Get body XML category list
     *
     * @return string
     */
    private function getBodyXmlCategoryList():string{
        $sessionKey = $this->webapiClient->getSessionKey();
        return sprintf('<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <getCategoryList xmlns="http://webapi.agrip.pl/">
                  <SessionKey>%s</SessionKey>
                  <Language>pl-PL</Language>
                </getCategoryList>
              </soap:Body>
            </soap:Envelope>', $sessionKey);
    }

    /**
     * Get content XML response
     *
     * @param string $body
     * @return string
     */
    private function getContentXmlResponse(string $body): string
    {
        $keyCache = 'deliverer-agrip_list_categories';
        return Cache::remember($keyCache, 3600, function() use (&$body){
            return $this->webapiClient->request($body);
        });
    }

    private function getCategories()
    {
        $body = $this->getBodyXmlCategoryList();
        $contentXmlResponse = $this->getContentXmlResponse($body);
        $crawler = $this->getCrawler($contentXmlResponse);
        $idNameMap = [];
        $crawler->filter('rowCategoryList')->each(function(Crawler $row) use (&$idNameMap){
            $id = $this->getTextCrawler($row->filter('id'));
            $name = $this->getTextCrawler($row->filter('name'));
            $treeCode = $this->getTextCrawler($row->filter('TreeCode'));
            $idNameMap[$id] = [
                'name' =>$name,
                'tree_code' => $treeCode,
            ];
        });
        $categories = [];
        foreach ($idNameMap as $item){
            $breadcrumbs = '';
            $explodeTreeCode = explode('\\', $item['tree_code']);
            $completed = true;
            foreach ($explodeTreeCode as $code){
                $name = $idNameMap[$code]['name']??'';
                if (!$name){
                    DelivererLogger::log('Not completed ' . $code);
                    $completed = false;
                }
                $breadcrumbs .= $breadcrumbs ? ' > ' : '';
                $breadcrumbs .= $name;
            }
            if ($completed){
                $item['breadcrumbs'] = $breadcrumbs;
                $categories[$breadcrumbs] = $item;
            }
        }
        return $categories;
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\SagitumWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use Symfony\Component\DomCrawler\Crawler;

class SagitumListProducts implements ListProducts
{
    use CrawlerHtml, NumberExtractor;

    const PER_PAGE = '12';

    /** @var SagitumWebsiteClient $websiteClient */
    protected $websiteClient;

    /**
     * SoapListProducts constructor
     *
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password)
    {
        $this->websiteClient = app(SagitumWebsiteClient::class, [
            'login' => $login,
            'password' => $password,
        ]);
    }

    /**
     * Get
     *
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     * @throws GuzzleException
     */
    public function get(?CategorySource $category = null): Generator
    {
        $products = $this->getProducts($category);
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get products
     *
     * @param CategorySource|null $category
     * @return Generator|ProductSource[]
     */
    private function getProducts(?CategorySource $category): Generator
    {

        $crawlerFirstPage = $this->getCrawlerFirstPage($category);
        $quantityPages = $this->getQuantityPages($crawlerFirstPage);
        for ($page = 1; $page <= $quantityPages; $page++) {
            $crawlerPage =$this->getCrawlerPage($category, $page);
            $products = $this->getProductsCrawlerPage($crawlerPage, $category);
            foreach ($products as $product) {
                yield $product;
            }
        }
    }

    /**
     * Get product
     *
     * @param Crawler $containerProduct
     * @param CategorySource $category
     * @return ProductSource|null
     */
    private function getProduct(Crawler $containerProduct, CategorySource $category): ?ProductSource
    {
        $id = $this->getIdProduct($containerProduct);
        DelivererLogger::log(sprintf('Product ID: %s.', $id));
        $price = $this->getPrice($containerProduct);
        if (!$price) {
            return null;
        }
        $stock = $this->getStock($containerProduct);
        $availability = 1;
        $url = sprintf('https://b2b.agrip.pl/Forms/Article.aspx?ArticleID=%s',$id);
        $product = new ProductSource($id, $url);
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setAvailability($availability);
        return $product;
    }

    /**
     * Get content page
     *
     * @param CategorySource $category
     * @param int $page
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerPage(CategorySource $category, int $page): Crawler
    {
        DelivererLogger::log(sprintf('Get data page %s, for category %s.', $page, $category->getId()));
        $dataAspx = $this->websiteClient->getLastDataAspx();
        $perPage = self::PER_PAGE;
        $pageIndex = $page -1;
        $pageIndexBackParam = ($pageIndex > 0) ? $pageIndex -1: 0;
        $pbParam = ($page === 1) ? 'PBF' : 'PBN';
        $pageIndexBackParam = ($page === 1) ? 1 : $pageIndexBackParam;
        $contents = $this->websiteClient->getContentAjax($category->getUrl(), [
            '_' => [
                'method' => 'POST'
            ],
            RequestOptions::FORM_PARAMS => [
                '__EVENTTARGET' => '',
                '__EVENTARGUMENT' => '',
                '__LASTFOCUS' => '',
                '__VIEWSTATE' => $dataAspx['view_state'],
                '__VIEWSTATEGENERATOR' => $dataAspx['view_state_generator'],
                '__PREVIOUSPAGE' => $dataAspx['previous_page'],
                'ctl00$txtSzukaj$State' => '{&quot;validationState&quot;:&quot;&quot;}',
                'ctl00$txtSzukaj' => '',
                'ctl00$cbxGroupMain$State' => '{&quot;validationState&quot;:&quot;&quot;}',
                'cbxGroupMain_VI' => '',
                'ctl00$cbxGroupMain' => '',
                'ctl00$cbxGroupMain$DDDState' => '{&quot;windowsState&quot;:&quot;0:0:-1:0:0:0:-10000:-10000:1:0:0:0&quot;}',
                'ctl00$cbxGroupMain$DDD$L$State' => '{&quot;CustomCallback&quot;:&quot;&quot;}',
                'ctl00$cbxGroupMain$DDD$L' => '',
                'cbxSubGroup_VI' => '-1',
                'ctl00$cbxSubGroup' => 'Dowolna',
                'ctl00$cbxSubGroup$DDDState' => '{&quot;windowsState&quot;:&quot;0:0:-1:0:0:0:-10000:-10000:1:0:0:0&quot;}',
                'ctl00$cbxSubGroup$DDD$L$State' => '{&quot;CustomCallback&quot;:&quot;&quot;}',
                'ctl00$cbxSubGroup$DDD$L' => '-1',
                'cbxProducer_VI' => 'Dowolny',
                'ctl00$cbxProducer' => 'Dowolny',
                'ctl00$cbxProducer$DDDState' => '{&quot;windowsState&quot;:&quot;0:0:-1:0:0:0:-10000:-10000:1:0:0:0&quot;}',
                'ctl00$cbxProducer$DDD$L$State' => '{&quot;CustomCallback&quot;:&quot;&quot;}',
                'ctl00$cbxProducer$DDD$L' => 'Dowolny',
                'cbxLicence_VI' => '-1',
                'ctl00$cbxLicence' => 'Dowolna',
                'ctl00$cbxLicence$DDDState' => '{&quot;windowsState&quot;:&quot;0:0:-1:0:0:0:-10000:-10000:1:0:0:0&quot;}',
                'ctl00$cbxLicence$DDD$L$State' => '{&quot;CustomCallback&quot;:&quot;&quot;}',
                'ctl00$cbxLicence$DDD$L' => '-1',
                'ctl00$chkAvaiable' => 'C',
                'ctl00$chkOnlyWithPhotos' => 'I',
                'ctl00$ContentPlaceHolder1$chkImages' => 'C',
                'ContentPlaceHolder1_ddSortType_VI' => 'Kod (A-Z)',
                'ctl00$ContentPlaceHolder1$ddSortType' => 'Kod (A-Z)',
                'ctl00$ContentPlaceHolder1$ddSortType$DDDState' => '{&quot;windowsState&quot;:&quot;0:0:-1:0:0:0:-10000:-10000:1:0:0:0&quot;}',
                'ctl00$ContentPlaceHolder1$ddSortType$DDD$L' => 'Kod (A-Z)',
                'ctl00$ContentPlaceHolder1$ddQuantityPerPage' => $perPage,
                'ctl00$ContentPlaceHolder1$chkDostepne' => 'C',
                'ctl00$ContentPlaceHolder1$chkOnlyWithImages' => 'I',
                'ctl00$ContentPlaceHolder1$ASPxHiddenField1' => "{&quot;data&quot;:&quot;$perPage|#|#&quot;}",
                'ctl00$ContentPlaceHolder1$ASPxDataView1' => "{&quot;endlessPagingMode&quot;:0,&quot;pageSize&quot;:3,&quot;pi&quot;:0,&quot;ic&quot;:265,&quot;aspi&quot;:0,&quot;layout&quot;:0,&quot;pageIndex&quot;:$pageIndex,&quot;b&quot;:true,&quot;pageCount&quot;:23,&quot;pc&quot;:23,&quot;ps&quot;:0}",
                'DXScript' => '1_16,1_17,1_28,1_66,1_18,1_19,1_20,1_52,1_225,1_226,1_26,1_27,1_231,1_22,1_228,1_234,1_44,1_229,1_13,1_224,1_35,1_42,1_34,1_230,1_62,1_61,1_236',
                'DXCss' => '1_75,1_69,1_70,1_71,1_74,1_251,1_248,1_250,1_247,../Content/Style.css,../Content/bootstrap.min.css',
                '__CALLBACKID' => 'ctl00$ContentPlaceHolder1$ASPxDataView1',
                '__CALLBACKPARAM' => "c0:p$pageIndexBackParam:3:$pbParam",
                '__EVENTVALIDATION' => $dataAspx['event_validation'],
            ],
        ]);
        return $this->getCrawler($contents);
    }

    /**
     * Get products data page
     *
     * @param Crawler $crawlerPage
     * @param CategorySource $category
     * @return array
     */
    private function getProductsCrawlerPage(Crawler $crawlerPage, CategorySource $category): array
    {
        $products = [];
        $crawlerPage->filter('td.dxdvItem > div')->each(function (Crawler $containerProduct) use (&$products, &$category) {
            $product = $this->getProduct($containerProduct, $category);
            if ($product) {
                array_push($products, $product);
            }
        });
        return $products;
    }

    /**
     * Get crawler first page
     *
     * @param CategorySource|null $category
     * @return Crawler
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    private function getCrawlerFirstPage(?CategorySource $category): Crawler
    {
        $contents = $this->websiteClient->getContents($category->getUrl());
        return $this->getCrawler($contents);
    }

    /**
     * Get data price
     *
     * @param Crawler $containerProduct
     * @return float
     * @throws DelivererAgripException
     */
    private function getPrice(Crawler $containerProduct): float
    {
        $price = null;
        $containerProduct->filter('span.dxeBase')
            ->each(function (Crawler $span)use(&$price){
                $idAttr = $this->getAttributeCrawler($span, 'id');
                if (!$price && Str::contains($idAttr, '_lblDiscountPrice_')){
                    $text = $this->getTextCrawler($span);
                    $text = str_replace([' ', ';&nbsp;', 'PLN', '.'], '', $text);
                    $text = str_replace(',', '.',$text);
                    $price = $this->extractFloat($text);
                }
            });
        if ($price === null){
            throw new DelivererAgripException('Not found price.');
        }
        return $price;
    }

    /**
     * Get quantity pages
     *
     * @param Crawler $crawlerFirstPage
     * @return int
     * @throws DelivererAgripException
     */
    private function getQuantityPages(Crawler $crawlerFirstPage): int
    {
        $quantityPages = 1;
        $text = $this->getTextCrawler($crawlerFirstPage->filter('.dxp-summary'));
        if (Str::contains($text, ' z ')) {
            $text = explode(' z ', $text)[1];
            $text = explode(' (', $text)[0];
            $quantityPages = (int) trim($text);
        }
        if (!$quantityPages) {
            throw new DelivererAgripException('Incorrect quantity pages.');
        }
        return $quantityPages;
    }

    /**
     * Get stock
     *
     * @param Crawler $containerProduct
     * @return int
     * @throws DelivererAgripException
     */
    private function getStock(Crawler $containerProduct): int
    {
        $stock = 0;
        $html = $containerProduct->html();
        if (Str::contains($html, 'Towar w przyjęciu')){
            return 0;
        }
        $containerProduct->filter('span.dxeBase')
            ->each(function (Crawler $span)use(&$stock){
                $idAttr = $this->getAttributeCrawler($span, 'id');
                if (!$stock && Str::contains($idAttr, '_lblQuantity_')){
                    $text = $this->getTextCrawler($span);
                    $stock = $this->extractInteger($text);
                }
            });
        return $stock;
    }

    /**
     * Get unit
     *
     * @param Crawler $containerProduct
     * @return string
     */
    private function getUnit(Crawler $containerProduct): string
    {
        $text = $this->getAttributeCrawler($containerProduct->filter('td')->eq(8)->filter('span'), 'class');
        return str_replace([' ', ';&nbsp;'], '', $text);
    }

    /**
     * Get ID product
     *
     * @param Crawler $containerProduct
     * @return string
     * @throws DelivererAgripException
     */
    private function getIdProduct(Crawler $containerProduct): string
    {
        $href = $this->getAttributeCrawler($containerProduct->filter('a'), 'href');
        $articleId = explode('?ArticleID=', $href)[1];
        $articleId = explode('"', $articleId)[0];
        $articleId = (int) $articleId;
        if (!$articleId){
            throw new DelivererAgripException('Not found ID product.');
        }
        return (string) $articleId;
    }


}
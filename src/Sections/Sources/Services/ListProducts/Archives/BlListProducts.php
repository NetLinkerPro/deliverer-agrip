<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Exception;
use Generator;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Contracts\ListProducts;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\BlWebapiClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ExtensionExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FtpDownloader;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\HtmlDecimalUnicodeDecoder;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class BlListProducts implements ListProducts
{
    use CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor, ExtensionExtractor, HtmlDecimalUnicodeDecoder;

    /** @var BlWebapiClient $webapiClient */
    protected $webapiClient;

    /**
     * BlListProducts constructor
     *
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->webapiClient = app(BlWebapiClient::class, [
            'token' => $token,
        ]);
    }

    /**
     * Get
     *
     * @param CategorySource|null $category
     * @return Generator
     * @throws DelivererAgripException
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function get(?CategorySource $category = null): Generator
    {
        $products = $this->getDataProducts();
        foreach ($products as $product) {
            yield $product;
        }
    }

    /**
     * Get data products
     *
     * @return Generator
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws DelivererAgripException
     * @throws Exception
     */
    private function getDataProducts(): Generator
    {
        $dataProducts = $this->getBaselinkerProducts();
        foreach ($dataProducts as $dataProduct) {
            $id = $dataProduct['id'];
            $price = $dataProduct['price'];
            if (!$price) {
                continue;
            }
            $stock = $dataProduct['stock'];
            $url = sprintf('https://panel.baselinker.pl/%s', $id);
            $product = new ProductSource($id, $url);
            $product->setAvailability(1);
            $product->setStock($stock);
            $product->setPrice($price);
            yield $product;
        }
    }

    /**
     * Get Baselinker products
     *
     * @return array
     * @throws DelivererAgripException
     */
    private function getBaselinkerProducts(): array
    {
        $products = [];
        foreach (range(1, 100) as $page) {
            DelivererLogger::log(sprintf('Get products from page %s.', $page));
            $data = $this->webapiClient->sendRequest('getInventoryProductsList', [
                'inventory_id' => 1154,
                'page' => $page,
            ]);
            $pageProducts = $data['products'] ?? [];
            foreach ($pageProducts as $idProduct => $pageProduct) {
                $stock = (int)$pageProduct['stock'][sprintf('bl_%s', 1431)] ?? 0;
             $price = (float)collect($pageProduct['prices'])->first();
                $products[$idProduct] = [
                    'id' => $idProduct,
                    'stock' => $stock,
                    'price' => $price,
                ];
            }
            if (sizeof($pageProducts) < 1000) {
                break;
            }
        }
        return $products;
    }
}
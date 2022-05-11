<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\ListProducts\Archives;

use Exception;
use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\XmlFileReader;
use NetLinker\DelivererAgrip\Sections\Sources\Services\ListCategories\Contracts\ListCategories;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\CorpsWebsiteClient;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CleanerDescriptionHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FtpDownloader;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\NumberExtractor;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\ResourceRemember;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\XmlExtractor;

class CorpsListProducts implements ListCategories
{
    use CrawlerHtml, ResourceRemember, CleanerDescriptionHtml, NumberExtractor, FtpDownloader, XmlExtractor;

    /** @var string $loginFtp */
    protected $loginFtp;

    /** @var string $passwordFtp */
    protected $passwordFtp;

    /**
     * SupremisB2bListCategories constructor
     *
     * @param string $loginFtp
     * @param string $passwordFtp
     */
    public function __construct(string $loginFtp, string $passwordFtp)
    {
        $this->loginFtp = $loginFtp;
        $this->passwordFtp = $passwordFtp;
    }

    /**
     * Get
     *
     * @return Generator|ProductSource[]|array
     * @throws DelivererAgripException
     * @throws GuzzleException
     */
    public function get(): Generator
    {
        $products = $this->getXmlProducts();
        foreach ($products as $product){
            yield $product;
        }
    }

    /**
     * Get XML products
     *
     * @return Generator
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function getXmlProducts(): Generator
    {
        $uri = $this->downloadXmlFile();
        try{
            $xmlReader = new XmlFileReader($uri);
            $xmlReader->setTagNameProduct('Produkt');
            $xmlProducts = $xmlReader->read();
            foreach ($xmlProducts as $xmlProduct){
                $id = $this->getStringXml($xmlProduct->Symbol);
                $url = sprintf('https://b2b.agrip.pl/produkt/%s', $id);
                $price = (float) $xmlProduct->CenaNettoJM;
                $stock = (int) $xmlProduct->StanJM;
                $product = new ProductSource($id, $url);
                $product->setPrice($price);
                $product->setStock($stock);
                yield $product;
            }
        } catch (Exception $e){
            throw $e;
        } finally {
            unlink($uri);
        }
    }

    /**
     * Download XML file
     *
     * @return string
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws Exception
     */
    private function downloadXmlFile(): string
    {
        $path = storage_path('temp/deliverer_agrip/agrip.xml');
        $this->downloadFileFtp('ftp.poterek.nazwa.pl', $this->loginFtp, $this->passwordFtp, 'agrip.xml', $path);
        return $path;
    }

}
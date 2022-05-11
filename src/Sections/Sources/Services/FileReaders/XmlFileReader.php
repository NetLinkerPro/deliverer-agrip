<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FileTemp;
use Ramsey\Uuid\Uuid;
use XMLReader;

class XmlFileReader
{
    use FileTemp;

    protected $url;

    protected $limit;

    protected $downloadBefore;

    protected $verifyGuzzleHttp = false;

    protected $userAgentGuzzleHttp = 'NetLinker.pro';

    protected $tagNameProduct = 'o';

    protected $count;

    protected $stopped;

    /** @var string|null $compress */
    protected $compress;

    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * @throws DelivererAgripException
     * @throws GuzzleException
     * @throws Exception
     */
    public function read(callable $cb = null){
        $reader = new XMLReader();
        $uri = $this->url;
        $this->stopped = false;
        $tempPathFileXml =null;
        if ($this->downloadBefore){
            Log::debug(sprintf('Download XML file from url: %1$s.', $uri));
            $client = new Client(['verify' => $this->verifyGuzzleHttp ]);
            $tempPathFileXml = $this->getPathTemp('xml_product_reader.xml');
            $client->request('GET', $uri, [
                'sink' => $tempPathFileXml,
                'headers' => [
                    'User-Agent' => $this->userAgentGuzzleHttp,
                ]
            ]);
            $uri = $tempPathFileXml;
        }
        stream_context_set_default(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        if ($this->compress){
            $uri = sprintf('compress.%s://%s', $this->compress, $uri);
        }
        $reader->open($uri, 'UTF-8', LIBXML_PARSEHUGE);
        $countRead = 0;
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == $this->tagNameProduct) {
                $xml = $reader->readOuterXML();
                $product = simplexml_load_string($xml);
                if ($cb){
                    $cb($product, $xml);
                }
                yield $product;
                $countRead++;
                if ($this->limit && $countRead >= $this->limit){
                    break;
                }
                if ($this->stopped){
                    break;
                }
            }
        }
        $reader->close();
        if (!$countRead){
            throw new DelivererAgripException('Not found products in XML: ' . $uri);
        }
        if ($tempPathFileXml){
            Storage::disk('local')->delete($tempPathFileXml);
        }
    }

    public function count(){
        $this->count = 0;
        $this->read(function(){
            $this->count++;
        });
        return $this->count;
    }

    public function setLimit($limit): XmlFileReader
    {
        $this->limit = $limit;
        return $this;
    }

    public function stop(){
        $this->stopped = true;
    }

    public function setDownloadBefore($downloadBefore): XmlFileReader
    {
        $this->downloadBefore = $downloadBefore;
        return $this;
    }

    public function setVerifyGuzzleHttp(bool $verifyGuzzleHttp): XmlFileReader
    {
        $this->verifyGuzzleHttp = $verifyGuzzleHttp;
        return $this;
    }

    public function setTagNameProduct(string $tagNameProduct): XmlFileReader
    {
        $this->tagNameProduct = $tagNameProduct;
        return $this;
    }

    /**
     * Set compress
     *
     * @param string $compress
     */
    public function setCompress(string $compress = 'zlib'): void
    {
        $this->compress = $compress;
    }

}

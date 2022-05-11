<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Traits;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use Rap2hpoutre\FastExcel\FastExcel;

trait Urlable
{

    /**
     * Get URL XLSX
     *
     * @param $url
     * @param string $method
     * @return \Illuminate\Support\Collection
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
     */
    protected function getUrlXlsx($url, $method='GET'){
        $tempFile = $this->getUrlTempFile($url, $method);
        $xlsx =  (new FastExcel)->import($tempFile);
        unlink($tempFile);
        return $xlsx;

    }

    /**
     * Get URL temp file
     *
     * @param $url
     * @param $path
     * @param string $method
     */
    protected function getUrlTempFile($url, $method = 'GET'){
        $tempFile = tempnam(sys_get_temp_dir(), 'agrip-');
        $this->getUrlFile($url, $tempFile, $method);
        return $tempFile;
    }

    /**
     * Get URL file
     *
     * @param $url
     * @param $path
     * @param string $method
     */
    protected function getUrlFile($url, $path, $method = 'GET'){
        $client = $this->getClient();
        $client->request($method, $url, ['sink' => $path]);
    }

    /**
     * Get URL JSON
     *
     * @param $url
     * @param string $method
     * @return mixed
     */
    protected function getUrlJson($url, $method = 'GET'){
        $body = $this->getUrlBody($url, $method);
        return json_decode($body, true,512, JSON_UNESCAPED_UNICODE);
    }
    /**
     * Get URL body
     *
     * @param $url
     * @param string $method
     * @return false|\Psr\Http\Message\StreamInterface|string
     */
    protected function getUrlBody($url, $method = 'GET'){
        if (Str::startsWith($url, 'ftp://')){
            $url = str_replace('ftp://', '', $url);
            $explodeUrl = explode('/', $url, 2);
            $ftpHandle = ftp_connect($explodeUrl[0]);
            $tmpHandle = fopen('php://temp', 'r+');
            if (!ftp_login($ftpHandle, 'anonymous', '')) {
                throw new DelivererAgripException(sprintf('Can not login to FTP %1$s.', $url));
            }
            if (!ftp_pasv($ftpHandle, true)) {
                throw new DelivererAgripException(sprintf('Cannot switch to passive mode: %s', $url));
            }
            $path = sprintf('/%s', $explodeUrl[1]);
            if (ftp_fget($ftpHandle, $tmpHandle,$path, FTP_BINARY)) {
                rewind($tmpHandle);
            } else {
                throw new DelivererAgripException(sprintf('Can not open CSV for uri %1$s.', $url));
            }
            return stream_get_contents($tmpHandle);
        } else {
            $response = $this->getUrlResponse($url, $method);
            return $response->getBody();
        }
    }

    /**
     * Try or null
     *
     * @param $callback
     * @param int $tries
     * @param int $sleep
     * @param null $exception
     * @param bool $codeZeroReturnNull
     * @return mixed
     */
    public function tryOrNull($callback, $tries = 3, $sleep = 7, &$exception = null, bool $codeZeroReturnNull = false){

        for($i = 0 ; $i < $tries ; $i++){

            try{

                return $callback();

            } catch (\Throwable $e){
                $exception = $e;
                if ($exception->getCode() === 0){
                    return null;
                }
                sleep($sleep);
                if ($i >= $tries){
                    DelivererLogger::log('Image download error: ' . $e->getMessage());
                    return null;
                }
            }
        }
    }

    /**
     * Get URL response
     *
     * @param $url
     * @param string $method
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function getUrlResponse($url, $method = 'GET'){
        $client = $this->getClient();
        return $client->request($method, $url);
    }

    /**
     * Get client
     *
     * @return Client
     */
    protected function getClient(){
        return new Client(['verify' =>false, 'cookies'=>true]);
    }

}
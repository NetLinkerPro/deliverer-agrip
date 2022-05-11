<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders;

use Exception;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\FileTemp;

class CsvFileReader
{
    use FileTemp;

    /** @var bool $downloadBefore */
    protected $downloadBefore = false;

    /** @var string $delimiter */
    protected $delimiter = ';';

    /** @var bool $hasHeader */
    protected $hasHeader = true;

    /** @var array|null $header */
    protected $header;

    /** @var string $uri */
    protected $uri;

    /** @var int $limitRows */
    protected $limitRows;

    /** @var int $ttlCache */
    protected $ttlCache;

    /** @var bool $dirTempSys */
    protected $dirTempSys = false;

    /** @var string $loginFtp */
    protected $loginFtp;

    /** @var string $passwordFtp */
    protected $passwordFtp;

    /** @var string $remotePathFtp */
    protected $remotePathFtp;

    /**
     * Constructor
     *
     * @param string $uri
     */
    public function __construct(string $uri)
    {
        $this->uri = $uri;
    }


    /**
     * Get rows
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function getRows(): Generator
    {
        if ($this->remotePathFtp) {
            $fileHandle = $this->getFileFtpHandle($this->uri);
        } else {
            $fileHandle = $this->getFileHandle($this->uri);
        }
       $dataLines = $this->getDataLines($fileHandle);
       foreach ($dataLines as $dataLine){
           yield $dataLine['data'];
       }
        fclose($fileHandle);
    }

    /**
     * Get data lines
     */
    private function getDataLines($fileHandle): Generator
    {
        $this->header = null;
        $countRows = 0;

        while (($data = fgetcsv($fileHandle, 0, $this->delimiter)) !== false) {
            if ($this->hasHeader && !$this->header) {
                foreach ($data as $key => $column) {
                    if ($column) {
                        $this->header[$key] = $column;
                    } else {
                        $this->header[$key] = $key;
                    }
                }
            } else {
                if ($this->header) {
                    $this->addHeaderData($data);
                }
                yield [
                    'data' =>$data,
                    'count_rows' =>$countRows,
                ];
                $countRows++;
                if ($this->limitRows && $this->limitRows <= $countRows) {
                    break;
                }
            }
        }
    }

    /**
     * Get file handler
     *
     * @param string $uri
     * @return bool|resource
     * @throws GuzzleException
     * @throws Exception
     */
    private function getFileHandle(string $uri)
    {
        if ($this->downloadBefore) {
            $keyCache = sprintf('deliverer_agrip_csv_file_reader_%s', $uri);
            $fileCache = Cache::get($keyCache);
            if ($this->ttlCache && $fileCache && File::exists($fileCache)) {
                $uri = $fileCache;
            } else {
                $uri = $this->downloadFile($uri);
                Cache::put($keyCache, $uri, $this->ttlCache);
            }
        }
        $handle = fopen($uri, 'r');
        if (!$handle) {
            throw new Exception(sprintf('Can not open CSV for uri %1$s.', $uri));
        }
        return $handle;
    }

    /**
     * Set download before
     *
     * @param bool $downloadCsv
     * @return $this
     */
    public function setDownloadBefore(bool $downloadBefore)
    {
        $this->downloadBefore = $downloadBefore;
        return $this;
    }

    /**
     * Download file
     *
     * @param string $uri
     * @return string
     * @throws GuzzleException
     */
    private function downloadFile(string $uri)
    {
        DelivererLogger::log(sprintf('Download CSV file from url: %1$s.', $uri));
        $client = new Client(['verify' => false]);
        $tempUri = $this->getPathTemp('csv_reader.csv');
        if (Str::contains($tempUri, 'tests/../testbench/laravel/storage/app/temp/')) {
            $tempUri = tempnam(sys_get_temp_dir(), 'csv_reader');
        }
        $client->request('GET', $uri, [
            'sink' => $tempUri,
        ]);
        return $tempUri;
    }

    /**
     * Set delimiter
     *
     * @param string $delimiter
     * @return $this
     */
    public function setDelimiter(string $delimiter)
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Set has header
     *
     * @param bool $hasHeader
     * @return $this
     */
    public function setHasHeader(bool $hasHeader)
    {
        $this->hasHeader = $hasHeader;
        return $this;
    }

    /**
     * Add header data
     *
     * @param array $data
     */
    private function addHeaderData(array &$data)
    {
        $newData = [];
        foreach ($data as $key => $value) {
            $newData[$this->header[$key]] = $value;
        }
        $data = $newData;
    }

    /**
     * Set limit rows
     *
     * @param int $limitRows
     * @return $this;
     */
    public function setLimitRows(int $limitRows)
    {
        $this->limitRows = $limitRows;
        return $this;
    }

    /**
     * Set TTL cache
     *
     * @param int $ttlCache
     */
    public function setTtlCache(int $ttlCache): void
    {
        $this->ttlCache = $ttlCache;
    }

    /**
     * Get file FTP handle
     *
     * @param string $uri
     * @throws Exception
     */
    private function getFileFtpHandle(string $uri)
    {
        $ftpHandle = ftp_connect($this->uri);
        $tmpHandle = fopen('php://temp', 'r+');
        if (!ftp_login($ftpHandle, $this->loginFtp, $this->passwordFtp)) {
            throw new Exception(sprintf('Can not login to FTP %1$s.', $uri));
        }
        if (!ftp_pasv($ftpHandle, true)) {
            throw new Exception(sprintf('Cannot switch to passive mode: %s', $uri));
        }
        if (ftp_fget($ftpHandle, $tmpHandle, $this->remotePathFtp, FTP_ASCII)) {
            rewind($tmpHandle);
        } else {
            throw new Exception(sprintf('Can not open CSV for uri %1$s.', $uri));
        }
        return $tmpHandle;
    }

    /**
     * Set login FTP
     *
     * @param string $loginFtp
     */
    public function setLoginFtp(string $loginFtp): void
    {
        $this->loginFtp = $loginFtp;
    }

    /**
     * Set password FTP
     *
     * @param string $passwordFtp
     */
    public function setPasswordFtp(string $passwordFtp): void
    {
        $this->passwordFtp = $passwordFtp;
    }

    /**
     * Set remote path FTP
     *
     * @param string $remotePathFtp
     */
    public function setRemotePathFtp(string $remotePathFtp): void
    {
        $this->remotePathFtp = $remotePathFtp;
    }
}

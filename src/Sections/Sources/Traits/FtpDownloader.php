<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;

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

trait FtpDownloader
{
    /**
     * Download
     *
     * @param string $host
     * @param string $login
     * @param string $password
     * @param string $remotePath
     * @param string $path
     * @throws Exception
     */
    public function downloadFileFtp(string $host, string $login, string $password, string $remotePath, string $path): void{
        $this->createDirectory($path);
        $ftpHandle = ftp_connect($host);
        if (!ftp_login($ftpHandle, $login, $password)) {
            throw new Exception(sprintf('Can not login to FTP %1$s.', $host));
        }
        if (!ftp_pasv($ftpHandle, true)) {
            throw new Exception(sprintf('Cannot switch to passive mode: %s', $host));
        }
        if (!ftp_get($ftpHandle, $path, $remotePath, FTP_BINARY)) {
            throw new Exception(sprintf('Can not open file for host %s.', $host));
        }
        ftp_close($ftpHandle);
    }

    /**
     * Create directory
     *
     * @param string $path
     */
    private function createDirectory(string $path): void
    {
        $dir = dirname($path);
        if (!File::exists($dir)){
            mkdir($dir, 0777, true);
        }
    }
}

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

class Csv2FileReader
{

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

    /** @var string $fromEncoding */
    private $fromEncoding;

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
       $dataLines = $this->getDataLines();
       foreach ($dataLines as $dataLine){
           $data = $dataLine['data'];
           if ($data['kod wlasny'] && $data['cena netto'] && $data['nazwa'] && $data['vat']){
               yield $data;
           }
       }
    }

    /**
     * Get data lines
     */
    private function getDataLines(): Generator
    {
        $contents = file_get_contents($this->uri);
        if ($this->fromEncoding){
            $contents = mb_convert_encoding($contents, 'UTF8', $this->fromEncoding);
        }
        $contents = str_replace(['﻿'], '', $contents);
        $lines = str_getcsv($contents, PHP_EOL);

        $this->header = null;
        $countRows = 0;

        foreach ($lines as $data){
            $data = str_getcsv($data, $this->delimiter);
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
     * Set from encoding
     *
     * @param string $fromEncoding
     */
    public function setFromEncoding(string $fromEncoding): void
    {
        $this->fromEncoding = $fromEncoding;
    }
}

<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services\FileReaders;

use NetLinker\DelivererAgrip\Sections\Sources\Services\FileReaders\CsvFileReader;
use NetLinker\DelivererAgrip\Tests\TestCase;

class CsvFileReaderTest extends TestCase
{
    public function testCacheFile()
    {
        $reader = new CsvFileReader(env('URL_1'));
        $reader->setDownloadBefore(true);
        $reader->setTtlCache(3600);
        $reader->eachRow(function(){});
    }
}

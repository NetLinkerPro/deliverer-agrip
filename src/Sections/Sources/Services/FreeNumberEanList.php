<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Services;

use Generator;
use Illuminate\Support\Facades\File;

class FreeNumberEanList
{
    const FILE_FREE_NUMBER_EAN = __DIR__ .'/../../../../resources/ean/free_numbers_eans.json';

    public function eans(): Generator
    {
        $data = json_decode(File::get(self::FILE_FREE_NUMBER_EAN), true, 512, JSON_UNESCAPED_UNICODE);
        foreach ($data as $ean){
            yield $ean;
        }
    }
}
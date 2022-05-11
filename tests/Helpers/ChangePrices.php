<?php

namespace NetLinker\DelivererAgrip\Tests\Helpers;

use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Tests\TestCase;

class ChangePrices extends TestCase
{
    const FILE = 'prices.json';

    /**
     * @test
     */
    public function runChange()
    {
        $contents = File::get(__DIR__.'/files/' . self::FILE);
        $json = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
        foreach ($json as &$item){
            $item['add_price'] = round($item['add_price'] * 1.1,2);
        }
        $newContents = json_encode($json, JSON_UNESCAPED_UNICODE);
        File::put(__DIR__ .'/files/out_' . self::FILE, $newContents);
    }
}
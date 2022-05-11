<?php

namespace NetLinker\DelivererAgrip\Tests\Helpers;

use Com\Tecnick\Barcode\Barcode;
use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Tests\TestCase;

class EanGenerator extends TestCase
{
    /**
     * @test
     */
    public function generate()
    {
        $quantity = 1500;
        $eans = [];
        $barcode = new Barcode();
        $from = 719205401510;
        while(sizeof($eans) <= $quantity){
            $bobj = $barcode->getBarcodeObj('EAN13',$from);
            $ean = $bobj->getExtendedCode();
            if (!in_array($ean, $eans)){
                dump($ean);
                array_push($eans, $ean);
            }
            $from++;
        }
        File::put(__DIR__.'/files/eans.txt', join(PHP_EOL, $eans));
    }
}
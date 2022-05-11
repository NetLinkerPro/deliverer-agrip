<?php


namespace NetLinker\DelivererAgrip\Tests\Alghoritms;


use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Tests\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class CorrectFilenameCloudImages extends TestCase
{

    private $storagePath = 'resources/deliverer-agrip/images/modele';

    /**
     * @test
     */
    public function correct()
    {
        $files = Storage::disk('production_wide_store')->files($this->storagePath);
        foreach ($files as $filepath){
            $newFilepath = str_replace('..', '.', $filepath);
            if (Str::contains($filepath, '..') && !Storage::disk('production_wide_store')->exists($newFilepath)){
                $newFilepath = str_replace('..', '.', $filepath);
                Storage::disk('production_wide_store')->move($filepath, $newFilepath);
                dump(sprintf('%s => %s', $filepath, $newFilepath));
                if (!Storage::disk('production_wide_store')->exists($newFilepath)){
                    echo "";
                }
            }
        }
        echo "";
    }
}
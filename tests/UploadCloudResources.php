<?php


namespace NetLinker\DelivererAgrip\Tests;


use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UploadCloudResources extends TestCase
{

    const FROM_DIR = '/home/karol/Pulpit/temp/DLA KLIENTÓW/Haki -logo';

    const TO_DIR = 'resources/deliverer-agrip/images/modele';

    /**
     * @test
     */
    public function upload(){
        $fromFiles = $this->getFromFiles();
        $countFiles = sizeof($fromFiles);
        foreach ($fromFiles as $index => $fromFile){
            dump(sprintf('File %s/%s', $countFiles, $index +1));
            $storagePath = sprintf('%s/%s', self::TO_DIR, $fromFile->getFilename());
            if (!Storage::exists($storagePath)){
                Storage::disk('production_wide_store')
                    ->put($storagePath, File::get($fromFile->getRealPath()));
            }
       }
    }

    /**
     * Get from files
     *
     * @return array
     */
    private function getFromFiles(): array
    {
        return File::files(self::FROM_DIR);
    }
}
<?php


namespace NetLinker\DelivererAgrip\Tests\Alghoritms;


use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Tests\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class CheckImages extends TestCase
{

    private $dirImages1 = '/home/karol/Pulpit/temp/DLA KLIENTÓW/Haki - znak wodny';

    private $dirImages2 = '/home/karol/Pulpit/temp/DLA KLIENTÓW/Haki -logo';

    /**
     * @test
     */
    public function check()
    {
        $arrayImgs = $this->getWithLogoImages();
        foreach (File::files($this->dirImages1) as $file){
           $filename =  str_replace('..', '.', $file->getFilename());
            if (!in_array($filename, $arrayImgs)){
                echo "";
            }
        }
        echo "";
    }

    /**
     * Get with logo images
     *
     * @return array
     */
    private function getWithLogoImages(): array
    {
        return array_map(function(SplFileInfo $item){
            return str_replace('..', '.', $item->getFilename());
        }, File::files($this->dirImages2));
    }
}
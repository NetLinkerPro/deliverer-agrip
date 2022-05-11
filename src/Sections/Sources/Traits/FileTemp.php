<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


use Exception;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

trait FileTemp
{

    /**
     * Get path temp
     *
     * @param string $filename
     * @return string
     * @throws Exception
     */
    public function getPathTemp(string $filename): string
    {
        $uuid = Uuid::uuid4();
        $path = Storage::disk('local')->path("temp/{$uuid}_{$filename}");
        $dirname = dirname($path);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        return $path;
    }
}
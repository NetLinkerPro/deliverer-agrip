<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;

use Closure;
use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;

trait ResourceRemember
{
    /**
     * Resource remember
     *
     * @param string $pathResource
     * @param int $ttl
     * @param Closure $callback
     * @return mixed
     */
    protected function resourceRemember(string $pathResource, int $ttl, Closure $callback)
    {
        if (File::exists($pathResource) && !$this->fileExpired($pathResource, $ttl)){
            $resource = $this->getResource($pathResource);
        } else {
            $resource = $callback();
            $this->putResource($pathResource, $resource);
        }
        return $resource;
    }

    /**
     * Get resource
     *
     * @param string $pathResource
     * @return mixed
     */
    private function getResource(string $pathResource)
    {
        $content = File::get($pathResource);
        return unserialize($content);
    }

    /**
     * File expired
     *
     * @param string $pathResource
     * @param int $ttl
     * @return bool
     */
    private function fileExpired(string $pathResource, int $ttl): bool
    {
        $modifiedDateFile = File::lastModified($pathResource);
        $dateNow = now()->timestamp;
        $diffMilliseconds = $dateNow - $modifiedDateFile;
        return $diffMilliseconds > $ttl;
    }

    /**
     * Put resource
     *
     * @param string $pathResource
     * @param $resource
     */
    private function putResource(string $pathResource, $resource): void
    {
        $this->createOrExistsDirectory($pathResource);
        $content = serialize($resource);
        File::put($pathResource, $content);
    }

    /**
     * Create or exists directory
     *
     * @param string $pathResource
     */
    private function createOrExistsDirectory(string $pathResource): void
    {
        $directory = dirname($pathResource);
        if (!File::exists($directory)){
            mkdir($directory, 0777, true);
        }
    }
}
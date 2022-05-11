<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


trait ExtensionExtractor
{

    /**
     * Extract extension
     *
     * @param string $url
     * @param string $default
     * @return string
     */
    public function extractExtension(string $url, string $default): string
    {
        $extension = explode('.', $url);
        $extension = end($extension);
        if ($extension){
            return $extension;
        }
        return $default;
    }
}
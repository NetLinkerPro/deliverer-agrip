<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


trait NumberExtractor
{

    /**
     * Extract integer
     *
     * @param string $content
     * @return int|null
     */
    protected function extractInteger(string $content): ?int{
        if (preg_match('/\d+/', $content, $matches)) {
            return (int)$matches[0];
        } else {
            return null;
        }
    }


    /**
     * Extract float
     *
     * @param string $content
     * @return float|null
     */
    protected function extractFloat(string $content): ?float{
        if (preg_match('/^[0-9]+(\\.[0-9]+)?$/', $content, $matches)) {
            return (float)$matches[0];
        } else {
            return null;
        }
    }
}
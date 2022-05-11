<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


trait LimitString
{

    /**
     * Limit reverse
     *
     * @param string $content
     * @param int $limit
     * @return string
     */
    protected function limitReverse(string $content, int $limit = 64): string{
        if (mb_strlen($content) > $limit){
            return mb_substr($content, -$limit);
        }
        return $content;
    }
}
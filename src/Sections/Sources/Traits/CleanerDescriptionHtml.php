<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;

use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;

trait CleanerDescriptionHtml
{

    /**
     * Clean attributes HTML
     *
     * @param string $html
     * @return string
     * @throws DelivererAgripException
     */
    public function cleanAttributesHtml(string $html): string
    {
        $html = trim(preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i",'<$1$2>', $html));
        if (!$this->checkIsValidHtml($html)){
            throw new DelivererAgripException('Html description is not valid.');
        }
        return $html;
    }

    /**
     * Clean empty tags HTML
     *
     * @param string $html
     * @return string
     * @throws DelivererAgripException
     */
    public function cleanEmptyTagsHtml(string $html): string
    {
        $html = htmlentities($html, null, 'utf-8');
        $html = str_replace('&nbsp;', ' ', $html);
        $html = html_entity_decode($html);
        $html = str_replace('&nbsp;', ' ', $html);
        do {
            $tmp = $html;
            $html = preg_replace("/<[a-z]+[^>]*>[\s|&nbsp;| ]*<\/[a-z]+>/", '', $html);
        } while ($html !== $tmp);
        if (!$this->checkIsValidHtml($html)){
            throw new DelivererAgripException('Html description is not valid.');
        }
        return trim($html);
    }

    /**
     * Check is valid HTML
     *
     * @param $string
     * @return bool
     */
    public function checkIsValidHtml($string): bool {
        return true;
    }

}

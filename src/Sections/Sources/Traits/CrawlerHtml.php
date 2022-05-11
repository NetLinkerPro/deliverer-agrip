<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;

use NetLinker\DelivererAgrip\Sections\Sources\Enums\TypeAttributeCrawler;
use Symfony\Component\DomCrawler\Crawler;

trait CrawlerHtml
{

    /**
     * Get crawler
     *
     * @param string $html
     * @return Crawler
     */
    protected function getCrawler(string $html): Crawler{
        $crawler = new Crawler();
        $crawler->addHtmlContent($html);
        return $crawler;
    }

    /**
     * Get attribute crawler
     *
     * @param Crawler $crawler
     * @param string $attribute
     * @param string $typeAttribute
     * @return string|null
     */
    protected function getAttributeCrawler(Crawler $crawler, string $attribute, string $typeAttribute = TypeAttributeCrawler::STRING_TYPE){
        if ($crawler->count()){
            $value = $crawler->first()->attr($attribute);
            switch($typeAttribute){
                case TypeAttributeCrawler::STRING_TYPE:
                    return strval($value);
                case TypeAttributeCrawler::INTEGER_TYPE:
                    return intval($value);
                default:
                    return $value;
            }
        } else {
            return null;
        }
    }

    /**
     * Get text crawler
     *
     * @param Crawler $crawler
     * @return string|null
     */
    protected function getTextCrawler(Crawler $crawler): ?string{
        if ($crawler->count()){
            $text = $crawler->first()->text();
            $text = str_replace(['&nbsp;', ' '], ' ', $text);
            return trim($text);
        } else {
            return null;
        }
    }
}
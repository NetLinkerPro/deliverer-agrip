<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


use SimpleXMLElement;

trait XmlExtractor
{

    /**
     * Get string XML
     *
     * @param SimpleXMLElement $xmlElement
     * @return string
     */
    protected function getStringXml(SimpleXMLElement $xmlElement): string{
        return  trim((string) $xmlElement);
    }


}
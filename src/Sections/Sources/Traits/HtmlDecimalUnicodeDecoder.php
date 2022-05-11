<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


trait HtmlDecimalUnicodeDecoder
{

    /**
     * Decode to charset UTF-8
     *
     * @param string $asciiContent
     * @return string
     */
    public function decodeToUtf8($content){


        $encodeCharacters = ['&#260;','&#262;','&#280;','&#321;','&#323;','&#211;','&#346;','&#377;','&#379;',
            '&#261;','&#263;','&#281;','&#322;','&#324;','&#243;','&#347;','&#378;','&#380;', '&Oacute;', '&oacute;'];
        $decodeCharacters = ['Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż','ą','ć','ę','ł','ń','ó','ś','ź','ż', 'Ó', 'ó'];
        return str_replace($encodeCharacters,$decodeCharacters,$content);
    }

}

<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;

trait FixJson
{
    /**
     * Fix JSON
     *
     * @param $json
     * @return string
     */
    public function fixJSON($json): string {
        $newJSON = '';

        $jsonLength = strlen($json);
        for ($i = 0; $i < $jsonLength; $i++) {
            if ($json[$i] == '"' || $json[$i] == "'") {
                $nextQuote = strpos($json, $json[$i], $i + 1);
                $quoteContent = substr($json, $i + 1, $nextQuote - $i - 1);
                $newJSON .= '"' . str_replace('"', "'", $quoteContent) . '"';
                $i = $nextQuote;
            } else {
                $newJSON .= $json[$i];
            }
        }

        return $newJSON;
    }
}
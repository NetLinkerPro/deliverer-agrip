<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


trait EanValidator
{

    /**
     * Is Valid EAN
     *
     * @param $barcode
     * @return bool
     */
    private function isValidEan($barcode): bool
    {
        $barcode = (string)$barcode;
        if (!preg_match("/^[0-9]+$/", $barcode)) {
            return false;
        }
        $l = strlen($barcode);
        if (!in_array($l, [8, 12, 13, 14, 17, 18]))
            return false;
        $check = substr($barcode, -1);
        $barcode = substr($barcode, 0, -1);
        $sum_even = $sum_odd = 0;
        $even = true;
        while (strlen($barcode) > 0) {
            $digit = substr($barcode, -1);
            if ($even)
                $sum_even += 3 * $digit;
            else
                $sum_odd += $digit;
            $even = !$even;
            $barcode = substr($barcode, 0, -1);
        }
        $sum = $sum_even + $sum_odd;
        $sum_rounded_up = ceil($sum / 10) * 10;
        return ($check == ($sum_rounded_up - $sum));
    }
}
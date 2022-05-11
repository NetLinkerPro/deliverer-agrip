<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


trait AssertHelper
{

    /**
     * Assert count between
     *
     * @param int $min
     * @param int $max
     * @param $values
     */
    public function assertCountBetween(int $min, int $max, $values){

        $size = sizeof($values);

        $this->assertTrue($size >= $min && $size <=$max);
    }
}
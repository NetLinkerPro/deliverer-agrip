<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Targets\Traits;

use NetLinker\DelivererAgrip\Sections\Targets\Traits\Urlable;
use NetLinker\DelivererAgrip\Tests\TestCase;

class UrlableTest extends TestCase
{
    use Urlable;

    public function testRun()
    {
        $url = 'ftp://95.50.174.142/76468/76468_1.jpg';
        $string = (string) $this->getUrlBody($url);
    }
}

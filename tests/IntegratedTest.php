<?php

namespace NetLinker\DelivererAgrip\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NetLinker\DelivererAgrip\Tests\Stubs\Owner;

class IntegratedTest extends TestCase
{

    use RefreshDatabase;

    public function testEnvFileForTestDatabase()
    {

        $o = Owner::all();
        echo '';
    }

}

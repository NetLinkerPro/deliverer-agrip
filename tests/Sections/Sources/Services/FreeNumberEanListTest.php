<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services;

use NetLinker\DelivererAgrip\Sections\Sources\Services\FreeNumberEanList;
use NetLinker\DelivererAgrip\Tests\TestCase;

class FreeNumberEanListTest extends TestCase
{
    public function test_list_ean(): void
    {
        /** @var FreeNumberEanList $service */
        $service = app(FreeNumberEanList::class);
        $eans = iterator_to_array($service->eans());
        $this->assertCount(9999, $eans);
    }
}
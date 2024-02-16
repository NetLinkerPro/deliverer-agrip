<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Services;

use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\AssignNumberEan;
use NetLinker\DelivererAgrip\Sections\Sources\Services\FreeNumberEanList;
use NetLinker\DelivererAgrip\Tests\TestCase;

class AssignNumberEanTest extends TestCase
{
    const FILE_ASSIGNED_NUMBER_EANS = __DIR__.'/assigned_numbers_eans.json';
    /**
     * @throws DelivererAgripException
     */
    public function test_assign_ean(): void
    {
        File::delete(self::FILE_ASSIGNED_NUMBER_EANS);

        AssignNumberEan::$fileAssignedNumbersEans = self::FILE_ASSIGNED_NUMBER_EANS;

        /** @var AssignNumberEan $service */
        $service = app(AssignNumberEan::class);
        $freeEan = $service->getFreeEan();
        $service->assign($freeEan, 'AR_1');

        $freeEan2 = $service->getFreeEan();
        $service->assign($freeEan2, 'AR_2');
        $eans = $service->assignedNumbersEans();
        $this->assertCount(2, $eans);
        $this->assertNotEquals($freeEan, $freeEan2);

        File::delete(self::FILE_ASSIGNED_NUMBER_EANS);
    }
}
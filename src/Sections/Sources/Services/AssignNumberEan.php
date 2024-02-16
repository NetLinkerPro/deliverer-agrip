<?php

namespace NetLinker\DelivererAgrip\Sections\Sources\Services;

use Generator;
use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\WideStore\Sections\Attributes\Models\Attribute;

class AssignNumberEan
{
    public static $fileAssignedNumbersEans = __DIR__ . '/../../../../resources/ean/assigned_numbers_eans';

    /** @var FreeNumberEanList $freeNumberEanListService  */
    private $freeNumberEanListService;

    public function __construct()
    {
        $this->freeNumberEanListService = app(FreeNumberEanList::class);
    }

    public function assignedNumbersEans(): array
    {
        $json = File::exists(self::$fileAssignedNumbersEans) ? File::get(self::$fileAssignedNumbersEans) : '{}';
        return json_decode($json, true, 512, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get free EAN
     *
     * @return string
     * @throws DelivererAgripException
     */
    public function getFreeEan(): string
    {
        $assignedEans = $this->assignedNumbersEans();
        foreach ($this->freeNumberEanListService->eans() as $ean){
            if (!isset($assignedEans[$ean]) && !Attribute::where('name', 'EAN')->where('value', $ean)->exists()){
                return $ean;
            }
        }
        throw new DelivererAgripException('Brak wolnych numerów EAN');
    }

    public function assign(string $ean, string $identifier, array $data = []): array
    {
        $data['ean'] = $ean;
        $data['identifier'] = $identifier;
        $data['assigned_at'] = now()->format('Y-m-d H:i:s');
        $assignedEans = $this->assignedNumbersEans();
        if (isset($assignedEans[$ean])){
            throw new DelivererAgripException('Kod EAN '.$ean.' jest już przypisany');
        }
        $assignedEans[$ean] = $data;
        File::put(self::$fileAssignedNumbersEans, json_encode($assignedEans, JSON_UNESCAPED_UNICODE));
        return $assignedEans;
    }
}
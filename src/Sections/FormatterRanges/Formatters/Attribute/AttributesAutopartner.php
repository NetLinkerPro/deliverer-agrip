<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\Attribute;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\IAction;

class AttributesAutopartner implements IAction
{

    public function action($value, $configuration, array $params)
    {

        if (in_array($value, ['EAN', 'SKU', 'Stan produktu', 'Gwarancja'])){
            return null;
        }
        $toReplace = [
            'Kod producenta' => 'Kod produktu',
            'Zabezpieczane elementy' =>'Zabezpieczane elementy podwozia',
            'Materiał' =>'Materiał osłony',
            'Grubość' => 'Grubość osłony',
            'Waga' => 'Waga osłony',
            'Malowanie' => 'Malowanie osłony',
            'Akcesoria montażowe' =>'Akcesoria montażowe dołączone do zestawu',
            'Obrabiarka CNC' =>'obrabiarka CNC',
            'Stal' => 'stal (bardzo wytrzymała i elastyczna)',
            'proszkowe, elektrostatyczne' => 'proszkowe (warstwa antykorozyjna), elektrostatyczne',
    ];
        foreach ($toReplace as $from=> $to){
            $value = str_replace($from, $to, $value);
        }
        return $value;
    }
}
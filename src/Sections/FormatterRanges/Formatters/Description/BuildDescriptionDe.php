<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\Description;


use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\IAction;
use NetLinker\WideStore\Sections\Attributes\Models\Attribute;

class BuildDescriptionDe implements IAction
{

    /**
     * Action
     *
     * @param mixed $value
     * @param $configuration
     * @param string $params
     * @return mixed
     */
    public function action($value, $configuration, array $params)
    {
        return $value;
        $attributes = $this->getAttributes($params);
        $html = $this->wrap('<strong>Producent:</strong> PARETO<br/><strong>Kod produktu:</strong> ' . $this->getAttributeValue('Kod producenta', $attributes)
            .'<br><br><strong>Osłona przeznaczona do auta:</strong>', 'p');
        $html .= $this->wrap($this->getHtmlFirstAttributes($attributes), 'ul');
        $html .= $this->wrap('<strong><br>Dane techniczne osłony:</strong><br>', 'p');
        $htmlLis = $this->getHtmlSecondAttributes($attributes);

        $htmlLis .= $this->wrap('<strong>Montaż:</strong> w otworach fabrycznych, nie trzeba nic przerabiać, nie narusza gwarancji auta.', 'li');

        $attr1 = $this->getAttributeValue('Posiada wycięcie na otwór spustowy miski olejowej', $attributes);
        if ($attr1 && $attr1 !== mb_strtolower('Nie')){
            $htmlLis .= $this->wrap('<strong>Posiada wycięcie na otwór spustowy miski olejowej</strong>, nie ma konieczności demontażu osłony przy wymianie oleju.<br><br>', 'li');
        }

        $html .= $this->wrap($htmlLis, 'ul');
        $html= $this->wrap($html, 'div');
        return $html;
    }

    private function wrap(string $html, $tag)
    {
        return sprintf('<%2$s>%1$s</%2$s>', $html, $tag);
    }

    private function getAttributes($params)
    {
        $attributes =  $params['attributes_source'];
        $attributes->push(new Attribute(['name' => 'Lata produkcji', 'value' => sprintf('%1$s - %2$s', $this->getAttributeValue('Rok produkcji od', $attributes), $this->getAttributeValue('Rok produkcji do', $attributes))]));
       foreach ($attributes as $attribute){
           Log::debug($attribute->name . ': ' . $attribute->value);
       }

        return $attributes;
    }

    private function getAttributeValue(string $attrName, $attributes)
    {
        return $attributes->where('name', $attrName)->first()->value ?? null;
    }

    private function getHtmlFirstAttributes($attributes)
    {
            $settings = [
            'attributes' => ['Marka', 'Model', 'Lata produkcji' => 'Lata produkcji', 'Rodzaje silników', 'Dodatkowe informacje'],
            'replace_values' => []
        ];

        return $this->buildHtmlFromAttributes($settings, $attributes);
    }

    private function getHtmlSecondAttributes($attributes)
    {
        $settings = [
            'attributes' => ['Zabezpieczane elementy' =>'Zabezpieczane elementy podwozia', 'Materiał' =>'Materiał osłony', 'Grubość' => 'Grubość osłony', 'Waga' => 'Waga osłony',
                'Malowanie' => 'Malowanie osłony',  'Technologia produkcji', 'Akcesoria montażowe' =>'Akcesoria montażowe dołączone do zestawu'],
            'replace_values' => [
                'Obrabiarka CNC' =>'obrabiarka CNC',
                'Stal' => 'stal (bardzo wytrzymała i elastyczna)',
                'proszkowe, elektrostatyczne' => 'proszkowe (warstwa antykorozyjna), elektrostatyczne',
            ]
        ];

        return $this->buildHtmlFromAttributes($settings, $attributes);
    }

    private function buildHtmlFromAttributes(array $settings, $attributes)
    {
        $html = '';

        foreach ($settings['attributes'] as $attrNameBefore => $attrNameAfter){
            if (!$attrNameBefore || is_numeric($attrNameBefore)){
                $attrNameBefore = $attrNameAfter;
            }
            $attributeValue = $this->getAttributeValue($attrNameBefore, $attributes);
            if ($attributeValue){
                foreach ($settings['replace_values'] ?? [] as $from => $to){
                    $attributeValue = str_replace($from, $to, $attributeValue);
                }
                $html .= sprintf('<li><strong>%1$s:</strong> %2$s</li>', $attrNameAfter, $attributeValue);
            } else {
                Log::debug('Not found attribute ' . $attrNameBefore);
            }
        }
        return $html;
    }
}
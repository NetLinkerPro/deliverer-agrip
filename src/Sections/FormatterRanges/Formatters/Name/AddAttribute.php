<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\Name;


use NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\IAction;
use NetLinker\WideStore\Sections\Attributes\Models\Attribute;

class AddAttribute implements IAction
{

    /**
     * Action
     *
     * @param mixed $value
     * @param array $configuration
     * @param string $params
     * @return mixed
     */
    public function action($value, array $configuration, array $params)
    {
        $attrName = $configuration['attr_name'] ?? '';
        $delimiter = $configuration['delimiter'] ??  ' ';
        $beforeAttr = $configuration['before_attr'] ??  '';
        $afterAttr = $configuration['afterAttr'] ??  '';

        if (!$attrName){
            return $value;
        }
        $productSourceUuid = $params['product_source_uuid'];
        $attr = Attribute::on('wide_store')->where('product_uuid', $productSourceUuid)->where('name', $attrName)->first();

        if (!$attr){
            return $value;
        }

        return sprintf('%1$s%2$s%3$s%4$s%5$s', $value, $delimiter, $beforeAttr, $attr->value, $afterAttr);
    }
}
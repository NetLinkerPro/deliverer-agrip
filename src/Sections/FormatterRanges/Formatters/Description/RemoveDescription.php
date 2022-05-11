<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\Description;


use NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\IAction;

class RemoveDescription implements IAction
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
        return '';
    }
}
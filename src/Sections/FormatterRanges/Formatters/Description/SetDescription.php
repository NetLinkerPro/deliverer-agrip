<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\Description;


use NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\IAction;

class SetDescription implements IAction
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
        return $configuration['html'] ?? '';
    }
}
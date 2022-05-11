<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters;


interface IAction
{

    /**
     * Action
     *
     * @param mixed $value
     * @param array $configuration
     * @param string $params
     * @return mixed
     */
    public function action($value, array $configuration, array $params);
}
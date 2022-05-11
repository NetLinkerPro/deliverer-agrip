<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\EachAfter;


use NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\IAction;

class ReplaceWords implements IAction
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
        foreach ($configuration as $from => $to){
            $value = str_replace($from, $to, $value);
        }
        return $value;
    }
}
<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Services;


use Illuminate\Support\Str;

class ActionDisplay
{

    /**
     * Prepare actions to display
     *
     * @param $actions
     */
    public function prepareActionsToDisplay(&$actions)
    {

        foreach ($actions as &$action){

            $action['name'] = __('deliverer-agrip::formatter-ranges.action_' . $action['action']);

            if ($action['configuration'] && !Str::startsWith($action['configuration'], '{') && !Str::endsWith($action['configuration'], '}')){
                continue;
            }

            $configuration = json_decode($action['configuration'], true, 512, JSON_UNESCAPED_UNICODE) ?? [];

            $action['configuration'] = json_encode($configuration,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) ?? [];
        }
    }
}
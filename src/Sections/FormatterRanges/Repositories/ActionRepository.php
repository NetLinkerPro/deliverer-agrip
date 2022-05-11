<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories;

use AwesIO\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Models\Action;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\DelivererAgrip\Sections\Formatters\Scopes\FormatterScopes;

class ActionRepository
{

    /**
     * Scope
     *
     * @param $request
     * @return \Illuminate\Support\Collection
     */
    public function scope($request)
    {
      return $this->get($request->range);

    }

    /**
     * Where in
     *
     * @param string $field
     * @param $values
     * @param $range
     * @return \Illuminate\Support\Collection
     */
    public function whereIn(string $field, $values, $range)
    {
        $actions = collect();

        foreach ($this->get($range) as $action){

            if (in_array($action->$field, $values)){
                $actions->push($action);
            }
        }

        return $actions;
    }

    /**
     * All actions
     *
     * @param $range
     * @return \Illuminate\Support\Collection
     */
    public function get($range)
    {

        $list = collect();

        $rangeStudly = Str::studly($range);

        foreach (glob(__DIR__. '/../Formatters/'.$rangeStudly.'/*.php') as $filename) {

            $filenameExplode = explode('/', $filename);

            if (sizeof($filenameExplode) && !is_dir($filename)) {

                $action = Str::snake(end($filenameExplode));
                $action = str_replace('.php', '', $action);

                $list->push(
                    new Action([
                        'name' => __('deliverer-agrip::formatter-ranges.action_' . $action),
                        'value' => $action
                    ])
                );

            }
        }

        return $list;
    }

}

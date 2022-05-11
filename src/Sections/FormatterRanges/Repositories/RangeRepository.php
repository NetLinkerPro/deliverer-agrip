<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories;

use AwesIO\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Models\Range;
use NetLinker\DelivererAgrip\Sections\Formatters\Scopes\FormatterScopes;

class RangeRepository
{

    /**
     * Scope
     *
     * @param $request
     * @return \Illuminate\Support\Collection
     */
    public function scope($request)
    {
      return $this->all();
    }

    /**
     * Find where
     *
     * @param array $conditions
     * @return \Illuminate\Support\Collection
     */
    public function findWhere(array $conditions)
    {

        $ranges = collect();

        foreach ($this->all() as $range){

            foreach ($conditions as $field => $value){

                if ($range->$field === $value){
                    $ranges->push($range);
                    break;
                }

            }
        }

        return $ranges;

    }

    /**
     * All ranges
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(){

        $list = collect();

        foreach (glob(__DIR__. '/../Formatters/*') as $filename) {

            $filenameExplode = explode('/', $filename);

            if (sizeof($filenameExplode) && is_dir($filename)) {

                $range = Str::snake(end($filenameExplode));

                $list->push(
                    new Range([
                        'name' => __('deliverer-agrip::formatter-ranges.range_' . $range),
                        'value' => $range
                    ])
                );

            }
        }

        return $list;
    }
}

<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Action extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
           return [
            'name' => $this->name,
               'value' => $this->value,
               'active' => $this->active,
               'configuration' => $this->configuration,
        ];
    }
}


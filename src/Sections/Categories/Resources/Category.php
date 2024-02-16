<?php

namespace NetLinker\DelivererAgrip\Sections\Categories\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Category extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'uri' => $this->uri,
            'ctx' => $this->ctx,
            'ctr' => $this->ctr,
            'item_id' => $this->item_id,
            'table_number' => $this->table_number,
            't' => $this->t,
            'data' => $this->data,
        ];
    }
}


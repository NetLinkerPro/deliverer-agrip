<?php

namespace NetLinker\DelivererAgrip\Sections\Formatters\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Formatter extends JsonResource
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
            'identifier_type' => $this->identifier_type,
            'name_lang' => $this->name_lang,
            'name_type' => $this->name_type,
            'url_type' => $this->url_type,
            'price_currency' => $this->price_currency,
            'price_type' => $this->price_type,
            'tax_country' => $this->tax_country,
            'stock_type' => $this->stock_type,
            'category_lang' => $this->category_lang,
            'category_type' => $this->category_type,
            'image_lang' => $this->image_lang,
            'image_type' => $this->image_type,
            'attribute_lang' => $this->attribute_lang,
            'attribute_type' => $this->attribute_type,
            'description_lang' => $this->attribute_lang,
            'description_type' => $this->attribute_type,
        ];
    }
}


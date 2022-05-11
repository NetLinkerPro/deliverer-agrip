<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use NetLinker\DelivererAgrip\Sections\Categories\Repositories\CategoryRepository;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories\ActionRepository;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories\RangeRepository;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Services\ActionDisplay;
use NetLinker\DelivererAgrip\Sections\Formatters\Repositories\FormatterRepository;
use NetLinker\DelivererAgrip\Sections\Formatters\Resources\Formatter;
use NetLinker\DelivererAgrip\Sections\ShopProducts\Repositories\ShopProductRepository;
use NetLinker\DelivererAgrip\Sections\Shops\Repositories\ShopRepository;
use NetLinker\DelivererAgrip\Sections\ShopProducts\Resources\ShopProduct;
use NetLinker\DelivererAgrip\Sections\Shops\Resources\Shop;

class FormatterRange extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {

        $formatter = Formatter::collection((new FormatterRepository())->findWhere(['uuid' => $this->formatter_uuid]))[0];
        $range = Range::collection((new RangeRepository())->findWhere(['value' => $this->range]))[0] ?? [];

        $actions = $this->actions;
        (new ActionDisplay())->prepareActionsToDisplay($actions);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'formatter_uuid' => $this->formatter_uuid,
            'formatter' => $formatter,
            'range' => $this->range,
            'range_object' => $range,
            'actions' => $actions,
        ];
    }
}


<?php


namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\AllAfter;


use NetLinker\DelivererPareto\Exceptions\DelivererParetoException;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Formatters\IAction;
use NetLinker\WideStore\Sections\ShopAttributes\Models\ShopAttribute;
use NetLinker\WideStore\Sections\ShopDescriptions\Models\ShopDescription;

class AddAttributesToDescription implements IAction
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
        $toOrderBefore = $configuration['to_order_before'] ?? null;
        $fromOrderAfter = $configuration['from_order_after'] ?? null;
        $productUuid = $params['product_uuid'] ?? null;

        if (!$productUuid) {
            throw new DelivererParetoException('Not found product UUID');
        }

        $attributesBefore = null;
        $attributesAfter = null;

        if ($toOrderBefore) {
            $attributesBefore = $this->getAttributesBefore($toOrderBefore, $productUuid);
        }
        if ($fromOrderAfter) {
            $attributesAfter = $this->getAttributesAfter($fromOrderAfter, $productUuid);
        }

        $description = $this->getDescription($productUuid);

        $descriptionContent = $this->buildDescriptionContent(optional($description)->description, $attributesBefore, $attributesAfter);

        $description->description = $descriptionContent;
        $description->save();

        return null;
    }

    /**
     * Get attributes before
     *
     * @param $toOrderBefore
     * @param $productUuid
     * @return mixed
     */
    private function getAttributesBefore($toOrderBefore, $productUuid)
    {
        return ShopAttribute::where('product_uuid', $productUuid)
            ->where('order', '<', $toOrderBefore)
            ->get();
    }

    /**
     * Get attributes after
     *
     * @param $fromOrderBefore
     * @param $productUuid
     * @return mixed
     */
    private function getAttributesAfter($fromOrderBefore, $productUuid)
    {
        return ShopAttribute::where('product_uuid', $productUuid)
            ->where('order', '>=', $fromOrderBefore)
            ->get();
    }

    /**
     * Get description
     *
     * @param $productUuid
     * @return mixed
     */
    private function getDescription($productUuid)
    {
        return ShopDescription::where('product_uuid', $productUuid)->first();
    }

    /**
     * Build description content
     *
     * @param $description
     * @param $attributesBefore
     * @param $attributesAfter
     * @return string
     */
    private function buildDescriptionContent($description, $attributesBefore, $attributesAfter)
    {
        $html = '<div class="description">';

        if ($attributesBefore) {

            $html .= '<ul class="attributes">';

            foreach ($attributesBefore as $attribute) {

                $html .= sprintf('<li class="attribute"><strong>%s:</strong> %s</li>', $attribute->name, $attribute->value);
            }

            $html .= '</ul>';
        }

        if ($description){
            $html .= sprintf('<p>%s</p>', $description);
        }

        if ($attributesAfter) {

            $html .= '<ul class="attributes">';

            foreach ($attributesAfter as $attribute) {

                $html .= sprintf('<li class="attribute"><strong>%s:</strong> %s</li>', $attribute->name, $attribute->value);
            }

            $html .= '</ul>';
        }

        return $html . '</div>';
    }
}
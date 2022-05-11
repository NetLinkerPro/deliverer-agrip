<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\Baselinker\Contracts;


use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;

interface StorageBaselinker
{
    /**
     * Get stock
     *
     * @param ShopProduct $shopProduct
     * @return int|null
     */
    public function getStock(ShopProduct $shopProduct): ?int;
}
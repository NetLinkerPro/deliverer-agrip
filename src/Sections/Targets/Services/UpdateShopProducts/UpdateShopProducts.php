<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateShopProducts;


use NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts\AddShopProducts;

class UpdateShopProducts extends AddShopProducts
{

    /**
     * Update shop products
     *
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function updateShopProducts(){
       return $this->addShopProducts();
    }

    /**
     * Get exist ptoducts
     *
     * @return array
     */
    public function getExistProducts()
    {
        return [];
    }


}
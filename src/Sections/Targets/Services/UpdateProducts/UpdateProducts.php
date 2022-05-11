<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateProducts;


use NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts\AddProducts;

class UpdateProducts extends AddProducts
{

    /** @var string $mode */
    protected $mode = 'update';

    /**
     * Update products
     *
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function updateProducts(){
        return $this->addProducts();
    }

    /**
     * Get exist products
     *
     * @return array
     */
    public function getExistProducts(): array
    {
        return [];
    }


}
<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\UpdatePricesStocks;


use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Sources\Services\Products\MoreProduct;
use NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateProducts\UpdateProducts;
use NetLinker\WideStore\Sections\Prices\Models\Price;
use NetLinker\WideStore\Sections\Products\Models\Product;
use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;
use NetLinker\WideStore\Sections\ShopStocks\Models\ShopStock;
use NetLinker\WideStore\Sections\Stocks\Models\Stock;
use NetLinker\WideStore\Sections\Taxes\Models\Tax;

class UpdatePricesStocks extends UpdateProducts
{

    /**
     * Update prices stocks
     *
     * @return \Generator
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function updatePricesStocks(){
       return $this->updateProducts();
    }

    /**
     * Add product
     *
     * @return \Generator
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function addProducts(){

        $this->initSettingsAndLogin();
        yield ['progress_now' => 1];

        yield ['progress_now' => 2];


        $products = $this->getProductsSource($this->getExistProducts());
        foreach ($products as $product){

            $this->addProduct($product);

            yield ['progress_now' => $this->countProducts + 2];
        }

    }


    /**
     * Add product
     *
     * @param array $productSource
     * @throws DelivererAgripException
     */
    public function addProduct(array $productSource)
    {
        throw new DelivererAgripException('Update prices stocks is not provider');

//        $productTarget = Product::where('deliverer', 'agrip')
//            ->where('identifier', $productSource['part_number'])
//            ->firstOrFail();
//
//        Price::where('deliverer', 'agrip')
//            ->where('product_uuid', $productTarget->uuid)
//            ->where('type', 'default')
//            ->where('currency', strtolower($productSource['currency']))
//            ->update([
//                'price' => $productSource['price_netto']
//            ]);
//
//        Tax::where('product_uuid', $productTarget->uuid)
//            ->where('country', 'pl')
//            ->update([
//                'tax' => $productSource['vat_rate']
//            ]);
//
//        Stock::where('product_uuid', $productTarget->uuid)
//            ->where('department', 'default')
//            ->where('type', 'default')
//            ->update([
//                'stock' => $productSource['available_qty'],
//            ]);
    }
}
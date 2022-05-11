<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts;

use Illuminate\Database\Eloquent\Model;
use NetLinker\DelivererAgrip\Sections\Formatters\Models\Formatter;
use NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Login;
use NetLinker\DelivererAgrip\Sections\Sources\Services\Products\ListProduct;
use NetLinker\WideStore\Sections\Identifiers\Models\Identifier;
use NetLinker\WideStore\Sections\ShopStocks\Models\ShopStock;
use NetLinker\WideStore\Sections\Stocks\Models\Stock;

class Stocks
{


    /** @var Formatter $formatter */
    private $formatter;

    /** @var $ownerUuid */
    private $ownerUuid;

    /** @var $shopUuid */
    private $shopUuid;

    /**
     * Constructor
     *
     * @param $ownerUuid
     * @param $shopUuid
     * @param Formatter $formatter
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function __construct($ownerUuid, $shopUuid, Formatter $formatter)
    {
        $this->formatter = $formatter;
        $this->ownerUuid = $ownerUuid;
        $this->shopUuid = $shopUuid;
    }

    /**
     * Add to database
     *
     * @param array $productSource
     * @param Model $shopProduct
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function add(Model $productSource, Model $shopProduct)
    {

        $stocksSource = $this->getStocks($productSource);

        foreach ($stocksSource as $stockSource) {

            ShopStock::updateOrCreate([
                'owner_uuid'=> $this->ownerUuid,
                'shop_uuid' => $this->shopUuid,
                'deliverer' => 'agrip',
                'product_uuid' => $shopProduct->uuid,
                'department' => $stockSource->department,
            ], [
                'stock' => $stockSource->stock,
                'availability' => $stockSource->availability,
            ]);

        }
    }

    /**
     * Get stocks
     *
     * @param $productSource
     * @return
     */
    public function getStocks($productSource)
    {
        return Stock::where('product_uuid', $productSource->uuid)
            ->where('deliverer', 'agrip')
            ->where('type', $this->formatter->stock_type)
            ->get();
    }


}
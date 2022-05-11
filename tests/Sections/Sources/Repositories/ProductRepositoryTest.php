<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Repositories;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\ProductRepository;
use NetLinker\DelivererAgrip\Tests\TestCase;

class ProductRepositoryTest extends TestCase
{

    public function testGetProducts()
    {
//        Artisan::call('cache:clear');
        /** @var ProductRepository $repository */
        $repository = app(ProductRepository::class);
        $repository->setBeforeDownload(false);
        $products = $repository->get(env('XML_URL'), env('LOGIN'), env('PASS'));
        foreach ($products as $product){
            Log::debug($product['id'] . ' '.$product['name']);
        }
    }

    public function testGetProductsForUpdatePriceAndStock()
    {
//        Artisan::call('cache:clear');
        /** @var ProductRepository $repository */
        $repository = app(ProductRepository::class);
        $repository->setBeforeDownload(false);
        $products = $repository->getForUpdatePriceStock(env('XML_URL'), env('LOGIN'), env('PASS'));
        foreach ($products as $product){
            if ($product['id'] === '11382'){
                Log::debug($product['id'] . ' '.$product['name']);
            }
        }
    }
}

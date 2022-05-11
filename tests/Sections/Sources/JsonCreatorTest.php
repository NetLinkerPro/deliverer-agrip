<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Sources\Services\DataProducts\PrestashopDataProducts;
use NetLinker\DelivererAgrip\Tests\TestCase;

class JsonCreatorTest extends TestCase
{
    public function testCreateJsonFile()
    {

        /** @var PrestashopDataProducts $dataProducts */
        $dataProducts = app(PrestashopDataProducts::class, [
            'urlApi' => env('URL_1'),
            'apiKey' => env('TOKEN'),
            'debug' =>false,
        ]);
        $products = [];
        $categories = [];
        $names = [];
        $descriptions = [];
        foreach ($dataProducts->get() as $product){
            $this->addCategories($product, $categories);
            $this->addNames($product, $names);
            $this->addDescriptions($product, $descriptions);
            $data = array_merge($categories, $names, $descriptions);
            $data = array_fill_keys($data, '');
            File::put(__DIR__ .'/language.json', json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            array_push($products, $product);
        }
        $this->assertNotEmpty($products);
    }

    private function addCategories(ProductSource $product, array &$categories)
    {
        $category = $product->getCategories()[0];
        while($category){
            $name = $category->getName();
            $name = trim($name);
            if (!in_array( $name, $categories)){
                array_push($categories, $name);
            }
            $category = $category->getChildren()[0] ?? null;
        }
    }

    private function addNames(ProductSource $product, array &$names)
    {
        if (!in_array('Figuren H:', $names)){
            array_push($names, 'Figuren H:');
        }
        if (!$product->hasProperty('name_pl')){
            $name = $product->getName();
            $name = sprintf('%s H:', explode(' H:', $name)[0]);
            $name = str_replace('Figuren H:', '', $name);
            $name = trim($name);
            if (!in_array($name, $names)){
                array_push($names, $name);
            }
        }
    }

    private function addDescriptions(ProductSource $product, array &$descriptions)
    {
        if ($product->hasProperty('description_raw')){
            $description = $product->getProperty('description_raw');
            $description = preg_replace('#<[^>]+>#', ' ', $description);
            $description = str_replace(["\n", '-wp', '</br>', '<br >', ' ', ',', ':', '/', 'cm', '[', ']', '(', ')', '-'], ' ', $description);
            $description = explode('Wir haben in offer über 500 Modelle', $description)[0];
            $sentences = ['Made in EU', 'Price für', 'hand gefertigt', 'sehr stabil', 'hand gemalt', 'Lieferung zeit', 'Wir haben in offer über 500 Modelle.'];
            foreach ($sentences as $sentence){
                if (!in_array($sentence, $descriptions)){
                    array_push($descriptions, $sentence);
                }
                $description = str_replace($sentence, ' ', $description);
            }
            foreach (explode(' ', $description) as $word){
                $word = trim($word);
                if ($word && mb_strlen($word) > 1 && !preg_match('~[0-9]+~', $word) && !in_array($word, $descriptions)) {
                    array_push($descriptions, $word);
                }
            }
        }
    }
}

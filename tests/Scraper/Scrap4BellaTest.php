<?php


namespace NetLinker\DelivererAgrip\Tests\Scraper;


use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;
use NetLinker\DelivererAgrip\Sections\Sources\Traits\CrawlerHtml;
use NetLinker\DelivererAgrip\Tests\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class Scrap4BellaTest extends TestCase
{
    use CrawlerHtml;

    private $products;
    private $productsSku;

    public function testRunScrap()
    {
        $categories =$this->getCategories();
        $this->loadProducts();
        foreach ($categories as $category){
            $this->addProductsByCategory($category);
        }
        echo "";
    }

    private function getClient()
    {
        return new Client(['verify' =>false, 'cookie' =>true]);
    }

    private function addProductsByCategory($category)
    {
        $deepestCategory = $this->getDeepestCategory($category);
        $pages = $this->getPages($deepestCategory);
        foreach (range(1, $pages) as $page){
            $this->addProductsByPageCategory($deepestCategory, $category, $page);
        }
        $this->saveProducts();
    }

    private function getPages(CategorySource $mainCategory)
    {
        $client = $this->getClient();
        $contents = $client->get($mainCategory->getUrl())->getBody()->getContents();
        $crawler = $this->getCrawler($contents);
        $pages = 1;
        $crawler->filter('.pagination .js-search-link')
            ->each(function(Crawler $aElement) use (&$pages){
               $pagesText = $this->getTextCrawler($aElement);
               $pagesInt = (int) $pagesText;
               $pages = $pagesInt > $pages ? $pagesInt : $pages;
            });
        return $pages;
    }

    private function getTreeCategoriesWebsite(Crawler $crawlerLiElements = null): array
    {
        if (!$crawlerLiElements){
            $crawlerLiElements = $this->getCrawlerByUrl('https://4bella.pl')->filter('.category-top-menu > li > .category-sub-menu > li');
        }
        $categories = [];
        $crawlerLiElements->each(function (Crawler $liElement) use (&$categories) {
            $aElement = $liElement->filterXPath('child::li/a');
            $href =$url= $this->getAttributeCrawler($aElement, 'href');
            $id = explode('-', explode('.pl/', $href)[1])[0];
            $name = $this->getTextCrawler($aElement);
            $category = new CategorySource($id, $name, $url);
            array_push($categories, $category);
            if ($this->liHasChild($liElement)){
                $liChildrenElements = $liElement->filterXPath('child::li/div/ul/li');
                $childrenCategories = $this->getTreeCategoriesWebsite($liChildrenElements);
                $category->setChildren($childrenCategories);
            }
        });
        return $categories;
    }

    private function liHasChild(Crawler $liElement): bool
    {
        return $liElement->filter('ul')->count() > 0;
    }

    public function getCrawlerByUrl($url, array $options = [])
    {
       $contents = $this->getContentsByUrl($url, $options);
        return $this->getCrawler($contents);
    }

    public function getContentsByUrl($url, array $options = [])
    {
        $client = $this->getClient();
        return $client->get($url, $options)->getBody()->getContents();
    }

    /**
     * Get list categories children
     *
     * @param $parentCategories
     * @param array $listCategoryDepth
     * @return array
     */
    private function getSingleCategories($parentCategories, array $listCategoryDepth = []): array
    {
        $depth = sizeof($listCategoryDepth) + 1;
        $listCategories = [];
        foreach ($parentCategories as $parentCategory){
            $listCategoryDepth[$depth] = $parentCategory;
            if (!$parentCategory->getChildren()){
                /** @var CategorySource|null $category */
                $category = null;
                /** @var CategorySource|null $categoryLast */
                $categoryLast = null;
                foreach ($listCategoryDepth as $categoryDepth){
                    $categoryDepthClone = clone $categoryDepth;
                    $categoryDepthClone->setChildren([]);
                    if (!$category){
                        $category = $categoryDepthClone;
                    } else {
                        $categoryLast->setChildren([$categoryDepthClone]);
                    }
                    $categoryLast = $categoryDepthClone;
                }
                array_push($listCategories, $category);
            } else {
                $listCategories = array_merge($listCategories, $this->getSingleCategories($parentCategory->getChildren(), $listCategoryDepth));
            }
        }
        unset($listCategoryDepth[$depth]);
        return $listCategories;
    }

    private function getCategories()
    {
        return Cache::remember('4bella_categories', 36000, function(){
            return $this->getSingleCategories( $this->getTreeCategoriesWebsite());
        });
    }

    private function getDeepestCategory(CategorySource $category):CategorySource
    {
        $categoryDeepest = $category;
        while($categoryDeepest){
            $categoryChild = $categoryDeepest->getChildren()[0] ?? null;
            if ($categoryChild){
                $categoryDeepest = $categoryChild;
            } else {
                break;
            }
        }
        return $categoryDeepest;
    }

    private function addProductsByPageCategory(CategorySource $deepestCategory, CategorySource $category, $page)
    {
        Log::info(sprintf('Get product from %s %s, page %s, size %s, size 2 %s.', $deepestCategory->getId(), $deepestCategory->getName(), $page, sizeof($this->products), sizeof($this->productsSku)));
        if ($deepestCategory->getId()==='133'){
            Log::info('Ignore category');
            return;
        }
        $bellaProducts = $this->getProducts($deepestCategory, $page);
       foreach ($bellaProducts as $bellaProduct){
           $imageUrl = $this->getImage($bellaProduct);
           $ean = $this->getEan($bellaProduct);
           $name = $bellaProduct['name'];
           $idFromName = $this->getIdFromName($bellaProduct);
           if ($ean && $imageUrl){
               $this->products[$ean] = [
                   'ean' =>$ean,
                   'category' => $category,
                   'image_url' =>$imageUrl,
                   'name' =>$name,
                   'id_from_name' =>$idFromName,
                   'tax' =>$bellaProduct['rate'],
               ];
           }
           if ($idFromName && $imageUrl){
               $this->productsSku[$idFromName] = [
                   'ean' =>$ean,
                   'category' => $category,
                   'image_url' =>$imageUrl,
                   'name' =>$name,
                   'id_from_name' => $idFromName,
                   'tax' =>$bellaProduct['rate'],
               ];
           }
       }
    }

    private function getProducts(CategorySource $deepestCategory, $page)
    {
        $urlCategory = $deepestCategory->getUrl();
        if (Str::endsWith($urlCategory, '/')){
            $urlCategory = Str::replaceLast('/', '', $urlCategory);
        }
        $url = sprintf('%s?page=%s&from-xhr',$urlCategory, $page);
        return Cache::remember($url, 36000, function() use (&$url){
            $contents = $this->getContentsByUrl($url, ['headers' => [
                'x-requested-with' =>'XMLHttpRequest',
                'accept' => 'application/json, text/javascript, */*; q=0.01',
            ]]);
            $data = json_decode($contents, true, 512, JSON_UNESCAPED_UNICODE);
            return $data['products'];
        });
    }

    private function getImage($bellaProduct)
    {
        $idImage = $bellaProduct['cover']['id_image'] ?? null;
        if (!$idImage){
            return null;
        }
        return sprintf('https://4bella.pl/%s/-.jpg', $idImage);
    }

    private function getEan($bellaProduct)
    {
        $urlProduct = $bellaProduct['url'];
        $ean = explode('-', $urlProduct);
        if (sizeof($ean) < 2){
            return null;
        }
        $ean = $ean[sizeof($ean) - 1];
        $ean = str_replace('.html', '.', $ean);
        $ean = (int) $ean;
        $ean = (string) $ean;
        if ($this->isValidBarcode($ean)){
            return $ean;
        } else {
            return null;
        }
    }

private function isValidBarcode($barcode) {
        $barcode = (string) $barcode;
        if (!preg_match("/^[0-9]+$/", $barcode)) {
            return false;
        }
        $l = strlen($barcode);
        if(!in_array($l, [8,12,13,14,17,18]))
            return false;
        $check = substr($barcode, -1);
        $barcode = substr($barcode, 0, -1);
        $sum_even = $sum_odd = 0;
        $even = true;
        while(strlen($barcode)>0) {
            $digit = substr($barcode, -1);
            if($even)
                $sum_even += 3 * $digit;
            else
                $sum_odd += $digit;
            $even = !$even;
            $barcode = substr($barcode, 0, -1);
        }
        $sum = $sum_even + $sum_odd;
        $sum_rounded_up = ceil($sum/10) * 10;
        return ($check == ($sum_rounded_up - $sum));
    }

    private function loadProducts()
    {
        $file = __DIR__ .'/../../resources/data/4bella_ean.txt';
        if (File::exists($file)){
            $content = File::get($file);
            $this->products = unserialize($content);
        } else {
            $this->products = [];
        }
        $file = __DIR__ .'/../../resources/data/4bella_sku.txt';
        if (File::exists($file)){
            $content = File::get($file);
            $this->productsSku = unserialize($content);
        } else {
            $this->productsSku = [];
        }
    }

    private function saveProducts()
    {
        $file = __DIR__ .'/../../resources/data/4bella_ean.txt';
        $content = serialize($this->products);
        $dir = dirname($file);
        if (!File::exists($dir)){
            mkdir($dir, 0777, true);
        }
        File::put($file, $content);

        $file = __DIR__ .'/../../resources/data/4bella_sku.txt';
        $content = serialize($this->productsSku);
        $dir = dirname($file);
        if (!File::exists($dir)){
            mkdir($dir, 0777, true);
        }
        File::put($file, $content);
    }


    private function getIdFromName($bellaProduct)
    {
        $skuExplode = explode('(', $bellaProduct['name']);
        $sku = $skuExplode[sizeof($skuExplode ) - 1] ?? '';
        $skuExplode = explode(')', $sku);
        return trim($skuExplode[0]);
    }

}
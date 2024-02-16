<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Targets\Traits\Imageable;
use NetLinker\DelivererAgrip\Sections\Targets\Traits\Urlable;
use NetLinker\WideStore\Sections\Images\Models\Image;
use NetLinker\WideStore\Sections\ShopImages\Models\ShopImage;
use NetLinker\WideStore\Sections\ShopProducts\Models\ShopProduct;

class Images
{
    use Imageable, Urlable;

    private $settings;

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     * @throws DelivererAgripException
     */
    public function add(ProductSource $product, Model $productTarget)
    {
        $this->deleteImageIfCan($product, $productTarget);
        $images = $product->getImages();
        foreach ($images as $index => &$image) {
            $path = sprintf('wide-store/images/agrip/%s', $image->getFilenameUnique());
            DelivererLogger::log('Get image product ' . $image->getUrl());
            if (Str::contains($image->getUrl(), '://localhost')){
                DelivererLogger::log('Not get image product for localhost url ' . $image->getUrl());
                continue;
            }
            $exception = null;
            $urlTarget = $this->tryOrNull(function() use (&$path, &$image){
                return $this->addOrUpdateDiskImage($path, $image, $image->getUrl(), 800, $image->getBackgroundFill(), $image->getExtension());
            }, 3, 15, $exception, true);
            if ($urlTarget){
                Image::updateOrCreate([
                    'deliverer' => 'agrip',
                    'product_uuid' => $productTarget->uuid,
                    'identifier' => $image->getId(),
                ], [
                    'url_source' => $image->getUrl(),
                    'path' => $path,
                    'disk' => 'wide_store',
                    'url_target' => $urlTarget,
                    'order' => $index + 5,
                    'main' => $image->isMain(),
                    'active' => true,
                    'lang' => $product->getLanguage(),
                    'type' => 'default',
                ]);
            } else {
                $codeResponse = $exception->getCode() ?? null;
                if ($codeResponse !== 404 && $codeResponse !== 0){
                    throw new DelivererAgripException(sprintf('Can not add image to cloud storage. Code response %s.', $codeResponse));
                } else {
                    DelivererLogger::log(sprintf('Error 404 response image: %s.',  $image->getUrl()));
                }
            }
            $image->setProperty('contents', '');
        }
    }

    private function deleteImageIfCan(ProductSource $product, Model $productTarget)
    {
        if (isset($this->settings()['update_exist_images_disk']) && $this->settings()['update_exist_images_disk']){
            /** @var Collection $images */
            $images = Image::where('deliverer', 'walor')
                ->where('product_uuid', $productTarget->uuid)
                ->get();
            foreach ($images as $image){
                Storage::disk('wide_store')->delete($image->path);
                $image->forceDelete();
            }
            $shopProducts = ShopProduct::where('deliverer', 'walor')
                ->where('source_uuid', $productTarget->uuid)->get();
            foreach ($shopProducts as $shopProduct){
                /** @var Collection $images */
                $shopImages = ShopImage::where('deliverer','walor')
                    ->where('product_uuid', $shopProduct->uuid)
                    ->get();
                foreach ($shopImages as $shopImage){
                    $shopImage->forceDelete();
                }
            }
        }
    }


    /**
     * Settings
     *
     * @return array|null
     */
    public function settings(): ?array
    {
        if (!$this->settings) {
            $this->settings = (new SettingRepository())->firstOrCreateValue();
        }
        return $this->settings;
    }

}
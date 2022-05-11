<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Sources\Classes\ProductSource;
use NetLinker\DelivererAgrip\Sections\Targets\Traits\Imageable;
use NetLinker\DelivererAgrip\Sections\Targets\Traits\Urlable;
use NetLinker\WideStore\Sections\Images\Models\Image;

class Images
{

    use Imageable, Urlable;

    /**
     * Add to database
     *
     * @param ProductSource $product
     * @param Model $productTarget
     * @throws DelivererAgripException
     */
    public function add(ProductSource $product, Model $productTarget)
    {
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

}
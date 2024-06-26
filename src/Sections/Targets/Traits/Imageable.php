<?php


namespace NetLinker\DelivererAgrip\Sections\Targets\Traits;


use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image as InterventionImage;
use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;

trait Imageable
{

    private $settings;

    /**
     * Get max width images disk
     *
     * @param int $defaultMaxWidth
     * @return int
     */
    public function getMaxWidthImagesDisk($defaultMaxWidth = 800){
        if (isset($this->settings()['max_width_images_disk']) && $this->settings()['max_width_images_disk']){
            return (int) $this->settings()['max_width_images_disk'];
        }
        return $defaultMaxWidth;
    }


    /**
     * Can update image disk
     *
     * @param $path
     * @return bool
     */
    public function canUpdateImageDisk($path){

        if (isset($this->settings()['update_exist_images_disk']) && $this->settings()['update_exist_images_disk']){
            return true;
        }

        return !Storage::disk('wide_store')->exists($path);
    }


    /**
     * Add or update disk image
     *
     * @param $path
     * @param $imageUrlSource
     * @param int $defaultMaxWidth
     * @param string|null $withFill
     * @param string $format
     * @return mixed
     */
    public function addOrUpdateDiskImage($path, $imageUrlSource, $defaultMaxWidth = 800, ?string $withFill = '#ffffff', string $format = 'jpg')
    {
        $canUpdateImageDisk = $this->canUpdateImageDisk($path);

        if ($canUpdateImageDisk) {

            $streamImage = $this->prepareStreamImage($imageUrlSource, $defaultMaxWidth, $withFill, $format);

            Storage::disk('wide_store')->put($path, $streamImage);
        }

        return Storage::disk('wide_store')->url($path);

    }

    /**
     * Prepare stream image
     *
     * @param $imageUrlSource
     * @param int $defaultMaxWidth
     * @param string|null $withFill
     * @return mixed
     */
    public function prepareStreamImage($imageUrlSource, $defaultMaxWidth = 800, ?string $withFill = '#ffffff', string $format = 'jpg')
    {
        $image = InterventionImage::make($this->getUrlBody($imageUrlSource));

        $image->resize($this->getMaxWidthImagesDisk($defaultMaxWidth), null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        if ($withFill){
            $image->fill($withFill, 0, 0);
        }
        return $image->stream($format, 85);
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
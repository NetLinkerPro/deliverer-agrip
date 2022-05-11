<?php


namespace NetLinker\DelivererAgrip\Sections\Settings\Repositories;


use Illuminate\Support\Facades\File;
use NetLinker\DelivererAgrip\Ownerable;
use NetLinker\WideStore\Sections\Settings\Models\Setting;

class SettingRepository extends \NetLinker\WideStore\Sections\Settings\Repositories\SettingRepository
{

    use Ownerable;
    /**
     * First or create value
     *
     * @param $value
     * @param string $key
     * @return  |null
     */
    public function updateOrCreateValue($value, $key = 'add_and_update_products')
    {
        return Setting::updateOrCreate([
            'deliverer' => 'agrip',
            'key' => $key,
        ], [
            'name' => __('deliverer-agrip::settings.name'),
            'value' => $value,
        ]);
    }

    /**
     * First or create value
     *
     * @param string $key
     * @return  |null
     */
    public function firstOrCreateValue($key = 'add_and_update_products')
    {
        return Setting::firstOrCreate([
            'deliverer' => 'agrip',
            'key' => $key,
        ], [
            'name' => __('deliverer-agrip::settings.name'),
            'value' => [],
        ])->value;
    }


}
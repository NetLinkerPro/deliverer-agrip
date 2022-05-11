<?php

namespace NetLinker\DelivererAgrip\Sections\Settings\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

use NetLinker\DelivererAgrip\Sections\Settings\Repositories\SettingRepository;
use NetLinker\DelivererAgrip\Sections\Settings\Requests\UpdateSetting;
use NetLinker\WideStore\Sections\Settings\Resources\Setting;

class SettingController extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** @var SettingRepository $settings */
    protected $settings;

    /**
     * Constructor
     *
     * @param SettingRepository $settings
     */
    public function __construct(SettingRepository $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Request index
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('deliverer-agrip::sections.settings.index', [
            'h1' => __('deliverer-agrip::settings.setting'),
            'value' => $this->settings->firstOrCreateValue(),
            'owners' => $this->settings->getOwners()->toArray(),
        ]);
    }

    /**
     * Update
     *
     * @param UpdateSetting $request
     * @param $id
     * @return array
     */
    public function update(UpdateSetting $request)
    {
        $this->settings->updateOrCreateValue($request->all());

        return notify(
            __('deliverer-agrip::settings.setting_was_successfully_updated'),
            $this->settings->firstOrCreateValue()
        );
    }

}

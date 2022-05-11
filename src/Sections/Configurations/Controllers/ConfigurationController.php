<?php

namespace NetLinker\DelivererAgrip\Sections\Configurations\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use NetLinker\DelivererAgrip\Sections\Configurations\Repositories\ConfigurationRepository;
use NetLinker\DelivererAgrip\Sections\Configurations\Requests\StoreConfiguration;
use NetLinker\DelivererAgrip\Sections\Configurations\Requests\UpdateConfiguration;
use NetLinker\DelivererAgrip\Sections\Configurations\Resources\Configuration;

class ConfigurationController extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** @var ConfigurationRepository $configurations */
    protected $configurations;

    /**
     * Constructor
     *
     * @param ConfigurationRepository $configurations
     */
    public function __construct(ConfigurationRepository $configurations)
    {
        $this->configurations = $configurations;
    }

    /**
     * Request index
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {

        return view('deliverer-agrip::sections.configurations.index', [
            'h1' => __('deliverer-agrip::configurations.configurations')
        ]);
    }

    /**
     * Request scope
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function scope(Request $request)
    {
        return Configuration::collection(
            $this->configurations->scopeOwner()->scope($request)
                ->latest()->smartPaginate()
        );
    }

    /**
     * Request store
     *
     * @param StoreConfiguration $request
     * @return array
     */
    public function store(StoreConfiguration $request)
    {
        $this->configurations->create($request->all());
        return notify(__('deliverer-agrip::configurations.configuration_was_successfully_updated'));
    }

    /**
     * Update
     *
     * @param UpdateConfiguration $request
     * @param $id
     * @return array
     */
    public function update(UpdateConfiguration $request, $id)
    {
        $this->configurations->scopeOwner()->update($request->all(), $id);

        return notify(
            __('deliverer-agrip::configurations.configuration_was_successfully_updated'),
            new Configuration($this->configurations->find($id))
        );
    }

    /**
     * Destroy
     *
     * @param Request $request
     * @param $id
     * @return array
     */
    public function destroy(Request $request, $id)
    {
        $this->configurations->scopeOwner()->destroy($id);

        return notify(__('deliverer-agrip::configurations.configuration_was_successfully_deleted'));
    }

}

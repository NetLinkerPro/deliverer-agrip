<?php

namespace NetLinker\DelivererAgrip\Sections\Formatters\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use NetLinker\DelivererAgrip\Sections\Formatters\Repositories\ActionRepository;
use NetLinker\DelivererAgrip\Sections\Formatters\Repositories\FormatterRepository;
use NetLinker\DelivererAgrip\Sections\Formatters\Repositories\RangeRepository;
use NetLinker\DelivererAgrip\Sections\Formatters\Requests\StoreFormatter;
use NetLinker\DelivererAgrip\Sections\Formatters\Requests\UpdateFormatter;
use NetLinker\DelivererAgrip\Sections\Formatters\Resources\Action;
use NetLinker\DelivererAgrip\Sections\Formatters\Resources\Formatter;
use NetLinker\DelivererAgrip\Sections\Formatters\Resources\Range;

class FormatterController extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** @var FormatterRepository $formatters */
    protected $formatters;

    /**
     * Constructor
     *
     * @param FormatterRepository $formatters
     */
    public function __construct(FormatterRepository $formatters)
    {
        $this->formatters = $formatters;
    }

    /**
     * Request index
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {

        return view('deliverer-agrip::sections.formatters.index', [
            'h1' => __('deliverer-agrip::formatters.formatters')
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
        return Formatter::collection(
            $this->formatters->scopeOwner()->scope($request)
                ->latest()->smartPaginate()
        );
    }

    /**
     * Request store
     *
     * @param StoreFormatter $request
     * @return array
     */
    public function store(StoreFormatter $request)
    {

        $this->formatters->create($request->all());
        return notify(__('deliverer-agrip::formatters.formatter_was_successfully_updated'));
    }

    /**
     * Update
     *
     * @param UpdateFormatter $request
     * @param $id
     * @return array
     */
    public function update(UpdateFormatter $request, $id)
    {
        $this->formatters->scopeOwner()->update($request->all(), $id);

        return notify(
            __('deliverer-agrip::formatters.formatter_was_successfully_updated'),
            new Formatter($this->formatters->find($id))
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
        $this->formatters->scopeOwner()->destroy($id);

        return notify(__('deliverer-agrip::formatters.formatter_was_successfully_deleted'));
    }


}

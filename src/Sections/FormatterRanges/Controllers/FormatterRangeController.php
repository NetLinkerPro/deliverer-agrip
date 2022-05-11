<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories\ActionRepository;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories\RangeRepository;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Resources\Action;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Resources\Range;
use NetLinker\FairQueue\Sections\Accesses\Requests\StoreAccess;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories\FormatterRangeRepository;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Requests\StoreFormatterRange;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Requests\UpdateFormatterRange;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Resources\FormatterRange;

class FormatterRangeController extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** @var FormatterRangeRepository $formatterRanges */
    protected $formatterRanges;

    /**
     * Constructor
     *
     * @param FormatterRangeRepository $formatterRanges
     */
    public function __construct(FormatterRangeRepository $formatterRanges)
    {
        $this->formatterRanges = $formatterRanges;
    }

    /**
     * Request index
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('deliverer-agrip::sections.formatter-ranges.index', [
            'h1' => __('deliverer-agrip::formatter-ranges.formatter_ranges')
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
        return FormatterRange::collection(
            $this->formatterRanges->scope($request)
                ->scopeOwner()
                ->latest()->smartPaginate()
        );
    }

    /**
     * Request store
     *
     * @param StoreFormatterRange $request
     * @return array
     */
    public function store(StoreFormatterRange $request)
    {

        $this->formatterRanges->create($request->all());
        return notify(__('deliverer-agrip::formatter-ranges.formatter_range_was_successfully_updated'));
    }

    /**
     * Update
     *
     * @param UpdateFormatterRange $request
     * @param $id
     * @return array
     */
    public function update(UpdateFormatterRange $request, $id)
    {

        $this->formatterRanges->scopeOwner()->update($request->all(), $id);

        return notify(
            __('deliverer-agrip::formatter-ranges.formatter_range_was_successfully_updated'),
            new FormatterRange($this->formatterRanges->find($id))
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
        $this->formatterRanges->scopeOwner()->destroy($id);

        return notify(__('deliverer-agrip::formatter-ranges.formatter_range_was_successfully_deleted'));
    }


    /**
     * Request ranges
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function ranges(Request $request)
    {
        return Range::collection(
            (new RangeRepository())->scope($request)
        );
    }


    /**
     * Request actions
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function actions(Request $request)
    {
        return Action::collection(
            (new ActionRepository())->scope($request)
        );
    }

}

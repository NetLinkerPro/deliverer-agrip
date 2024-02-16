<?php

namespace NetLinker\DelivererAgrip\Sections\Categories\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use NetLinker\DelivererAgrip\Sections\Categories\Repositories\ActionRepository;
use NetLinker\DelivererAgrip\Sections\Categories\Repositories\CategoryRepository;
use NetLinker\DelivererAgrip\Sections\Categories\Repositories\RangeRepository;
use NetLinker\DelivererAgrip\Sections\Categories\Requests\StoreCategory;
use NetLinker\DelivererAgrip\Sections\Categories\Requests\UpdateCategory;
use NetLinker\DelivererAgrip\Sections\Categories\Resources\Action;
use NetLinker\DelivererAgrip\Sections\Categories\Resources\Category;
use NetLinker\DelivererAgrip\Sections\Categories\Resources\Range;

class CategoryController extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** @var CategoryRepository $categories */
    protected $categories;

    /**
     * Constructor
     *
     * @param CategoryRepository $categories
     */
    public function __construct(CategoryRepository $categories)
    {
        $this->categories = $categories;
    }

    /**
     * Request index
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {

        return view('deliverer-agrip::sections.categories.index', [
            'h1' => 'Kategorie'
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
        return Category::collection(
            $this->categories->scopeOwner()->scope($request)
                ->latest()->smartPaginate()
        );
    }

    /**
     * Request store
     *
     * @param StoreCategory $request
     * @return array
     */
    public function store(StoreCategory $request)
    {
        $this->categories->create($request->all());
        dd($request->all());
        return
         notify(__('deliverer-agrip::categories.category_was_successfully_updated'));
    }

    /**
     * Update
     *
     * @param UpdateCategory $request
     * @param $id
     * @return array
     */
    public function update(UpdateCategory $request, $id)
    {
        $this->categories->scopeOwner()->update($request->all(), $id);

        return notify(
            __('deliverer-agrip::categories.category_was_successfully_updated'),
            new Category($this->categories->find($id))
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
        $this->categories->scopeOwner()->destroy($id);

        return notify(__('deliverer-agrip::categories.category_was_successfully_deleted'));
    }


}

<?php

namespace NetLinker\DelivererAgrip\Sections\Dashboard\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class DashboardController extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('deliverer-agrip::sections.dashboard.index',
            [
                'h1' => __('deliverer-agrip::dashboard.headline'),
            ]
        );
    }
}

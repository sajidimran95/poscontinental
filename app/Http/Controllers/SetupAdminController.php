<?php

namespace App\Http\Controllers;

use App\Services\SeedAdminService;
use Illuminate\View\View;

class SetupAdminController extends Controller
{
    public function show(SeedAdminService $seed): View
    {
        return view('setup.admin', [
            'locked' => $seed->adminExists(),
            'result' => null,
        ]);
    }

    public function store(SeedAdminService $seed): View
    {
        if ($seed->adminExists()) {
            return view('setup.admin', [
                'locked' => true,
                'result' => null,
            ]);
        }

        $result = $seed->ensure(false);

        return view('setup.admin', [
            'locked' => false,
            'result' => $result,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardService $dashboardService)
    {
        $refresh = $request->boolean('refresh');
        $data = $dashboardService->build($refresh);

        return view('admin.dashboard.index', [
            'd' => $data,
            'refreshed' => $refresh,
        ]);
    }
}

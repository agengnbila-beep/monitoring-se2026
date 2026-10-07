<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Models\Dashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPageController extends Controller
{
    /**
     * Laman utama: dashboard pertama (urut nama), atau keadaan kosong.
     */
    public function index(Request $request): View
    {
        return $this->render($request, Dashboard::orderBy('name')->first());
    }

    public function show(Request $request, Dashboard $dashboard): View
    {
        return $this->render($request, $dashboard);
    }

    private function render(Request $request, ?Dashboard $dashboard): View
    {
        return view('dashboard', [
            'dashboards' => Dashboard::orderBy('name')->get(['id', 'name', 'slug']),
            'dashboard' => $dashboard,
            'payload' => $dashboard ? (new DashboardResource($dashboard->load('widgets')))->resolve($request) : null,
        ]);
    }
}

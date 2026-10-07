<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Http\Resources\DashboardResource;
use App\Models\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DashboardResource::collection(Dashboard::orderBy('name')->get());
    }

    public function store(DashboardRequest $request): JsonResponse
    {
        $dashboard = Dashboard::create([
            ...$request->safe()->only('name', 'description', 'filters'),
            'slug' => $this->uniqueSlug($request->string('name')),
            'created_by' => $request->user()->id,
        ]);

        return (new DashboardResource($dashboard->load('widgets')))->response()->setStatusCode(201);
    }

    /**
     * Dashboard beserta widget-nya; data tiap widget diambil lewat `data_url`.
     */
    public function show(Dashboard $dashboard): DashboardResource
    {
        return new DashboardResource($dashboard->load('widgets'));
    }

    public function update(DashboardRequest $request, Dashboard $dashboard): DashboardResource
    {
        $dashboard->update($request->safe()->only('name', 'description', 'filters'));

        return new DashboardResource($dashboard->load('widgets'));
    }

    public function destroy(Dashboard $dashboard): Response
    {
        $dashboard->delete();

        return response()->noContent();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'dashboard';
        $slug = $base;

        for ($n = 2; Dashboard::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}

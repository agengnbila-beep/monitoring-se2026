<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WidgetRequest;
use App\Http\Resources\WidgetResource;
use App\Models\Dashboard;
use App\Models\SavedQuery;
use App\Models\Widget;
use App\Services\Dashboard\WidgetOptions;
use App\Services\Query\QueryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WidgetController extends Controller
{
    public function __construct(private QueryEngine $engine) {}

    public function store(WidgetRequest $request, Dashboard $dashboard): JsonResponse
    {
        $widget = $dashboard->widgets()->create($this->attributes($request));

        return (new WidgetResource($widget))->response()->setStatusCode(201);
    }

    public function update(WidgetRequest $request, Widget $widget): WidgetResource
    {
        $widget->update($this->attributes($request));

        return new WidgetResource($widget);
    }

    public function destroy(Widget $widget): Response
    {
        $widget->delete();

        return response()->noContent();
    }

    /**
     * Hasil saved query milik widget, format sama dengan /api/queries/{id}/run.
     */
    public function data(Widget $widget): JsonResponse
    {
        $savedQuery = $widget->savedQuery()->with('dataset')->firstOrFail();
        $result = $this->engine->cachedRun($savedQuery->dataset, $savedQuery->config);

        return response()->json([
            'data' => ['columns' => $result['columns'], 'rows' => $result['rows']],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(WidgetRequest $request): array
    {
        $savedQuery = SavedQuery::with('dataset')->findOrFail($request->integer('saved_query_id'));
        $columns = $this->engine->outputColumnNames($savedQuery->dataset, $savedQuery->config);

        return [
            ...$request->safe()->only('saved_query_id', 'title', 'chart_type', 'sort_order', 'width'),
            'options' => WidgetOptions::validate($request->input('chart_type'), $request->input('options'), $columns),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WidgetRequest;
use App\Http\Resources\WidgetResource;
use App\Models\Dashboard;
use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Models\Widget;
use App\Services\Dashboard\GlobalFilters;
use App\Services\Dashboard\WidgetOptions;
use App\Services\Query\SavedQueryRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WidgetController extends Controller
{
    private const MAX_FILTER_OPTIONS = 500;

    public function __construct(private SavedQueryRunner $runner) {}

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

    /**
     * Simpan urutan baru semua widget dashboard (hasil drag and drop).
     * `order` wajib berisi tepat semua id widget milik dashboard ini.
     */
    public function reorder(Request $request, Dashboard $dashboard): JsonResponse
    {
        $ids = $dashboard->widgets()->pluck('id')->all();

        $validated = $request->validate([
            'order' => ['required', 'array', 'size:'.count($ids)],
            'order.*' => ['integer', 'distinct', Rule::in($ids)],
        ], [
            'order.size' => 'Urutan harus memuat semua widget dashboard.',
            'order.*.in' => 'Widget tidak ada di dashboard ini.',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $position => $id) {
                Widget::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['data' => ['order' => $validated['order']]]);
    }

    public function destroy(Widget $widget): Response
    {
        $widget->delete();

        return response()->noContent();
    }

    /**
     * Hasil saved query milik widget, format sama dengan /api/queries/{id}/run.
     * Parameter `filters[key]` (filter global dashboard) ditambahkan ke filter query;
     * `meta.filters_applied` dan `meta.filters_ignored` memberi tahu UI mana yang berlaku.
     */
    public function data(Request $request, Widget $widget): JsonResponse
    {
        $widget->load('dashboard', 'savedQuery.dataset');
        $savedQuery = $widget->savedQuery;

        $global = GlobalFilters::forColumns(
            $widget->dashboard,
            $this->runner->filterableColumns($savedQuery),
            (array) $request->query('filters', []),
        );

        $result = $this->runner->run($savedQuery, $global['filters']);

        return response()->json([
            'data' => ['columns' => $result['columns'], 'rows' => $result['rows']],
            'meta' => [...$result['meta'], 'filters_applied' => $global['applied'], 'filters_ignored' => $global['ignored']],
        ]);
    }

    /**
     * Pilihan nilai untuk filter global bertipe select: nilai unik kolom `key`
     * dari semua dataset yang dipakai widget dashboard ini.
     */
    public function filterOptions(Dashboard $dashboard, string $key): JsonResponse
    {
        $definition = collect($dashboard->filters ?? [])->firstWhere('key', $key);
        abort_unless($definition !== null && $definition['type'] === 'select', 404);

        $values = Dataset::query()
            ->whereIn('id', SavedQuery::whereIn('id', $dashboard->widgets()->select('saved_query_id'))->whereNotNull('dataset_id')->select('dataset_id'))
            ->get()
            ->filter(fn (Dataset $dataset) => $dataset->isImported() && $dataset->columns()->where('name', $key)->exists())
            ->flatMap(fn (Dataset $dataset) => DB::table($dataset->table_name)
                ->whereNotNull($key)
                ->distinct()
                ->orderBy($key)
                ->limit(self::MAX_FILTER_OPTIONS)
                ->pluck($key))
            ->map(fn (mixed $value) => (string) $value)
            ->unique()
            ->sort(SORT_NATURAL)
            ->take(self::MAX_FILTER_OPTIONS)
            ->values();

        return response()->json(['data' => $values]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(WidgetRequest $request): array
    {
        $savedQuery = SavedQuery::with('dataset')->findOrFail($request->integer('saved_query_id'));
        $columns = $this->runner->outputColumns($savedQuery);

        return [
            ...$request->safe()->only('saved_query_id', 'title', 'chart_type', 'sort_order', 'width'),
            'options' => WidgetOptions::validate($request->input('chart_type'), $request->input('options'), $columns),
        ];
    }
}

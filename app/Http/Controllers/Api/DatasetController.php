<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetColumnResource;
use App\Http\Resources\DatasetResource;
use App\Models\Dataset;
use App\Models\DatasetColumn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DatasetController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DatasetResource::collection(Dataset::latest()->get());
    }

    public function profile(Dataset $dataset): JsonResponse
    {
        $this->ensureImported($dataset);

        return response()->json([
            'data' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'row_count' => $dataset->row_count,
                'column_count' => $dataset->column_count,
                'profiled_at' => $dataset->profiled_at?->toIso8601String(),
                'columns' => DatasetColumnResource::collection($dataset->columns()->get()),
            ],
        ]);
    }

    public function preview(Request $request, Dataset $dataset): JsonResponse
    {
        $this->ensureImported($dataset);

        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $columns = $dataset->columns()->get();

        $rows = DB::table($dataset->table_name)
            ->orderBy('_row_id')
            ->limit($validated['limit'] ?? 20)
            ->get($columns->pluck('name')->all());

        return response()->json([
            'data' => [
                'columns' => $columns->map(fn (DatasetColumn $column) => $column->only('name', 'label', 'type')),
                'rows' => $rows,
            ],
        ]);
    }

    private function ensureImported(Dataset $dataset): void
    {
        abort_unless($dataset->status === 'done', 409, 'Dataset belum selesai diimpor.');
    }
}

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

        $rows = $dataset->previewRows($columns->pluck('name')->all(), $validated['limit'] ?? 20);

        return response()->json([
            'data' => [
                'columns' => $columns->map(fn (DatasetColumn $column) => $column->only('name', 'label', 'type')),
                'rows' => $rows,
            ],
        ]);
    }

    private function ensureImported(Dataset $dataset): void
    {
        abort_unless($dataset->isImported(), 409, 'Dataset belum selesai diimpor.');
    }
}

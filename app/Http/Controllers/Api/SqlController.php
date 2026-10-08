<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedQuery;
use App\Services\Query\SqlRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sub-menu SQL di Analitik: menjalankan dan menyimpan file Query SQL (read-only).
 */
class SqlController extends Controller
{
    public function __construct(private SqlRunner $runner) {}

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:20000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.SqlRunner::MAX_LIMIT],
        ], [
            'sql.required' => 'Tulis query SQL terlebih dahulu.',
        ]);

        $result = $this->runner->run($validated['sql'], $validated['limit'] ?? SqlRunner::DEFAULT_LIMIT);

        return response()->json([
            'data' => ['columns' => $result['columns'], 'rows' => $result['rows']],
            'meta' => $result['meta'],
        ]);
    }

    public function showFile(SavedQuery $savedQuery): JsonResponse
    {
        $this->ensureSql($savedQuery);

        return response()->json(['data' => $this->fileData($savedQuery)]);
    }

    public function storeFile(Request $request): JsonResponse
    {
        $file = SavedQuery::create([
            ...$this->validateFile($request),
            'type' => SavedQuery::TYPE_SQL,
            'config' => [],
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->fileData($file)], 201);
    }

    public function updateFile(Request $request, SavedQuery $savedQuery): JsonResponse
    {
        $this->ensureSql($savedQuery);

        $savedQuery->update($this->validateFile($request));

        return response()->json(['data' => $this->fileData($savedQuery)]);
    }

    /**
     * SQL dicoba dijalankan (1 baris) sebelum disimpan, agar file yang
     * tersimpan pasti bisa dipakai widget.
     *
     * @return array{name: string, description: string|null, folder_id: int|null, sql: string}
     */
    private function validateFile(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'folder_id' => ['nullable', 'integer', Rule::exists('query_folders', 'id')],
            'sql' => ['required', 'string', 'max:20000'],
        ], [
            'name.required' => 'Nama file wajib diisi.',
            'sql.required' => 'Tulis query SQL terlebih dahulu.',
        ]);

        $this->runner->run($validated['sql'], 1);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'folder_id' => $validated['folder_id'] ?? null,
            'sql' => $validated['sql'],
        ];
    }

    private function ensureSql(SavedQuery $savedQuery): void
    {
        abort_unless($savedQuery->isSql(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function fileData(SavedQuery $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'description' => $file->description,
            'folder_id' => $file->folder_id,
            'sql' => $file->sql,
            'updated_at' => $file->updated_at?->toIso8601String(),
        ];
    }
}

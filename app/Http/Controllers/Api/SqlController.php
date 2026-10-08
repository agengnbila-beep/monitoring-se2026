<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QueryFolder;
use App\Models\SavedQuery;
use App\Services\Query\SqlRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Mode SQL (mirip Metabase): folder berisi file SQL, editor, dan eksekusi read-only.
 */
class SqlController extends Controller
{
    public function __construct(private SqlRunner $runner) {}

    /**
     * Seluruh pohon folder dan file dalam satu respons.
     */
    public function tree(): JsonResponse
    {
        return response()->json([
            'data' => [
                'folders' => QueryFolder::orderBy('name')->get(['id', 'name', 'parent_id']),
                'files' => SavedQuery::where('type', SavedQuery::TYPE_SQL)->orderBy('name')
                    ->get(['id', 'name', 'folder_id', 'updated_at'])
                    ->map(fn (SavedQuery $file) => [
                        'id' => $file->id,
                        'name' => $file->name,
                        'folder_id' => $file->folder_id,
                        'updated_at' => $file->updated_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

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

    public function storeFolder(Request $request): JsonResponse
    {
        $folder = QueryFolder::create([
            ...$this->validateFolder($request),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $folder->only('id', 'name', 'parent_id')], 201);
    }

    public function updateFolder(Request $request, QueryFolder $folder): JsonResponse
    {
        $validated = $this->validateFolder($request, $folder);

        $folder->update($validated);

        return response()->json(['data' => $folder->only('id', 'name', 'parent_id')]);
    }

    public function destroyFolder(QueryFolder $folder): Response
    {
        abort_if(
            $folder->children()->exists() || $folder->queries()->exists(),
            409,
            'Folder masih berisi folder atau file. Pindahkan atau hapus isinya dulu.',
        );

        $folder->delete();

        return response()->noContent();
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

    public function destroyFile(SavedQuery $savedQuery): Response
    {
        $this->ensureSql($savedQuery);
        abort_if($savedQuery->widgets()->exists(), 409, 'File SQL masih dipakai widget dashboard.');

        $savedQuery->delete();

        return response()->noContent();
    }

    /**
     * @return array{name: string, parent_id: int|null}
     */
    private function validateFolder(Request $request, ?QueryFolder $folder = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:query_folders,id'],
        ], [
            'name.required' => 'Nama folder wajib diisi.',
        ]);

        $parent = isset($validated['parent_id']) ? QueryFolder::find($validated['parent_id']) : null;

        abort_if($folder !== null && $folder->containsOrIs($parent), 422, 'Folder tidak bisa dipindah ke dalam dirinya sendiri.');

        return ['name' => $validated['name'], 'parent_id' => $parent?->id];
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

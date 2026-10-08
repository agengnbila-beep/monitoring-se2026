<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QueryFolder;
use App\Models\SavedQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Ruang Analitik: folder (bisa bersarang) berisi file analitik
 * bertipe Filter Data (`builder`) maupun Query SQL (`sql`).
 */
class AnalyticsController extends Controller
{
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
        $folder->update($this->validateFolder($request, $folder));

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

    /**
     * Pindahkan file analitik (jenis apa pun) ke folder lain atau ke akar.
     */
    public function moveFile(Request $request, SavedQuery $savedQuery): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', Rule::exists('query_folders', 'id')],
        ]);

        $savedQuery->update(['folder_id' => $validated['folder_id'] ?? null]);

        return response()->json(['data' => $savedQuery->only('id', 'name', 'folder_id')]);
    }

    public function destroyFile(SavedQuery $savedQuery): Response
    {
        abort_if($savedQuery->widgets()->exists(), 409, 'File masih dipakai widget dashboard.');

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
}

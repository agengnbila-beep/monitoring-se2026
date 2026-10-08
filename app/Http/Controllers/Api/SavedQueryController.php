<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedQueryRequest;
use App\Http\Resources\SavedQueryResource;
use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Services\Query\QueryEngine;
use App\Services\Query\SavedQueryRunner;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SavedQueryController extends Controller
{
    public function __construct(private QueryEngine $engine) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $queries = SavedQuery::with('dataset:id,name')
            ->when($request->integer('dataset_id'), fn ($query, int $datasetId) => $query->where('dataset_id', $datasetId))
            ->latest()
            ->get();

        return SavedQueryResource::collection($queries);
    }

    public function store(SavedQueryRequest $request): JsonResponse
    {
        $dataset = Dataset::findOrFail($request->integer('dataset_id'));

        $savedQuery = SavedQuery::create([
            ...$request->safe()->only('dataset_id', 'name', 'description', 'folder_id'),
            'config' => $this->config(fn () => $this->engine->normalize($dataset, $request->input('config'))),
            'created_by' => $request->user()->id,
        ]);

        return (new SavedQueryResource($savedQuery->load('dataset:id,name')))->response()->setStatusCode(201);
    }

    public function show(SavedQuery $savedQuery): SavedQueryResource
    {
        return new SavedQueryResource($savedQuery->load('dataset:id,name'));
    }

    public function update(SavedQueryRequest $request, SavedQuery $savedQuery): SavedQueryResource
    {
        $dataset = Dataset::findOrFail($request->integer('dataset_id'));

        $savedQuery->update([
            ...$request->safe()->only('dataset_id', 'name', 'description', 'folder_id'),
            'config' => $this->config(fn () => $this->engine->normalize($dataset, $request->input('config'))),
        ]);

        return new SavedQueryResource($savedQuery->load('dataset:id,name'));
    }

    public function destroy(SavedQuery $savedQuery): Response
    {
        abort_if($savedQuery->widgets()->exists(), 409, 'Query masih dipakai widget dashboard.');

        $savedQuery->delete();

        return response()->noContent();
    }

    /**
     * Jalankan konfigurasi yang belum disimpan (pratinjau di query builder).
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'config' => ['present', 'array'],
        ]);

        $dataset = Dataset::findOrFail($validated['dataset_id']);

        return $this->result($this->config(fn () => $this->engine->cachedRun($dataset, $validated['config'])));
    }

    public function runSaved(SavedQuery $savedQuery, SavedQueryRunner $runner): JsonResponse
    {
        return $this->result($runner->run($savedQuery->loadMissing('dataset')));
    }

    /**
     * Error validasi dari QueryEngine diberi awalan `config.` agar
     * menunjuk ke field yang dikirim klien.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws ValidationException
     */
    private function config(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ["config.{$key}" => $messages])->all()
            );
        }
    }

    /**
     * @param  array{columns: list<array<string, string>>, rows: list<array<string, mixed>>, meta: array<string, mixed>}  $result
     */
    private function result(array $result): JsonResponse
    {
        return response()->json([
            'data' => ['columns' => $result['columns'], 'rows' => $result['rows']],
            'meta' => $result['meta'],
        ]);
    }
}

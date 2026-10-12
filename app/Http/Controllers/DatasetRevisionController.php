<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetFileRequest;
use App\Jobs\ApplyDatasetRevision;
use App\Models\Dataset;
use App\Models\DatasetRevision;
use App\Services\Import\ColumnNamer;
use App\Services\Import\DatasetImpact;
use App\Services\Import\DatasetPreview;
use App\Services\Import\ReaderFactory;
use App\Services\Import\XlsxReader;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sub-menu "Update data": mengganti isi dataset yang sudah ada dengan file baru.
 * Semua Filter Data, file SQL, dan widget dashboard yang memakai dataset itu
 * ikut memakai data baru setelah pembaruan selesai.
 */
class DatasetRevisionController extends Controller
{
    public function create(Request $request): View
    {
        return view('data.revisions.create', [
            'datasets' => Dataset::where('status', 'done')->whereNotNull('table_name')->orderBy('name')->get(['id', 'name', 'row_count', 'updated_at']),
            'selected' => $request->integer('dataset') ?: null,
            'revisions' => DatasetRevision::with('dataset:id,name', 'creator:id,name')->latest('id')->take(10)->get(),
        ]);
    }

    public function store(DatasetFileRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'dataset_id' => ['required', 'integer', Rule::exists('datasets', 'id')->where('status', 'done')],
        ], [
            'dataset_id.required' => 'Pilih dataset yang akan diperbarui.',
            'dataset_id.exists' => 'Dataset tidak ditemukan atau belum selesai diimpor.',
        ]);

        $dataset = Dataset::findOrFail($validated['dataset_id']);

        if ($dataset->revisions()->whereIn('status', ['pending', 'processing'])->exists()) {
            return $this->fail($request, 'Dataset ini sedang diperbarui. Tunggu sampai selesai.');
        }

        $file = $request->file('file');
        $type = strtolower($file->getClientOriginalExtension());

        $revision = $dataset->revisions()->create([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $file->storeAs('uploads', Str::uuid().'.'.$type, 'local'),
            'file_type' => $type,
            'file_size' => $file->getSize(),
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('data.revisions.preview', $revision)], 201);
        }

        return redirect()->route('data.revisions.preview', $revision);
    }

    /**
     * Pratinjau file baru, perbandingan kolom, dan daftar yang terdampak.
     */
    public function preview(Request $request, DatasetRevision $revision): View
    {
        abort_unless($revision->status === 'uploaded', 404);

        $dataset = $revision->dataset;
        $sheets = [];
        $sheet = null;
        $preview = ['headers' => [], 'rows' => []];
        $error = null;

        try {
            $path = Storage::disk('local')->path($revision->file_path);
            $sheets = $revision->file_type === 'xlsx' ? (new XlsxReader($path))->sheetNames() : [];
            $sheet = $request->query('sheet', $sheets[0] ?? null);
            $preview = DatasetPreview::make(ReaderFactory::make($path, $revision->file_type, $sheet));

            if ($preview['headers'] === []) {
                $error = 'File tidak berisi data.';
            }
        } catch (Exception $e) {
            $error = 'File tidak bisa dibaca: '.$e->getMessage();
        }

        $current = $dataset->columns()->pluck('name')->all();
        $incoming = $preview['headers'] === [] ? [] : ColumnNamer::sanitize($preview['headers']);
        $removed = array_values(array_diff($current, $incoming));

        return view('data.revisions.preview', [
            'revision' => $revision,
            'dataset' => $dataset,
            'sheets' => $sheets,
            'sheet' => $sheet,
            'preview' => $preview,
            'error' => $error,
            'added' => array_values(array_diff($incoming, $current)),
            'removed' => $removed,
            'impact' => DatasetImpact::ofRemovedColumns($dataset, $removed),
        ]);
    }

    public function confirm(Request $request, DatasetRevision $revision): RedirectResponse
    {
        abort_unless($revision->status === 'uploaded', 404);

        $path = Storage::disk('local')->path($revision->file_path);
        $sheets = $revision->file_type === 'xlsx' ? (new XlsxReader($path))->sheetNames() : [];

        $validated = $request->validate([
            'sheet' => $sheets === [] ? ['prohibited'] : ['required', Rule::in($sheets)],
        ]);

        $revision->update(['sheet_name' => $validated['sheet'] ?? null, 'status' => 'pending']);

        ApplyDatasetRevision::dispatch($revision);

        return redirect()->route('data')
            ->with('success', "Pembaruan \"{$revision->dataset->name}\" masuk antrean. Dashboard tetap memakai data lama sampai pembaruan selesai.");
    }

    public function destroy(DatasetRevision $revision): RedirectResponse
    {
        abort_unless($revision->status === 'uploaded', 404);

        Storage::disk('local')->delete($revision->file_path);
        $revision->delete();

        return redirect()->route('data.revisions.create')->with('success', 'Pembaruan dibatalkan.');
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['dataset_id' => [$message]]], 422)
            : back()->withErrors(['dataset_id' => $message]);
    }
}

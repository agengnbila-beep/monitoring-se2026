<?php

namespace App\Http\Controllers;

use App\Jobs\ImportDataset;
use App\Models\Dataset;
use App\Services\Import\DatasetPreview;
use App\Services\Import\ReaderFactory;
use App\Services\Import\XlsxReader;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DatasetController extends Controller
{
    public function index(): View
    {
        return view('data.index', [
            'datasets' => Dataset::latest()->get(),
        ]);
    }

    /**
     * Form upload memakai XHR (agar ada progress bar) dan meminta JSON;
     * tanpa JavaScript, form biasa tetap berfungsi lewat redirect.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,xlsx,json', 'max:20480'],
        ], [
            'file.required' => 'Pilih file yang akan diupload.',
            'file.uploaded' => 'File gagal diupload. Ukurannya mungkin melebihi batas server.',
            'file.extensions' => 'Format file harus CSV, XLSX, atau JSON.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
        ]);

        $file = $request->file('file');
        $type = strtolower($file->getClientOriginalExtension());

        $dataset = Dataset::create([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $file->storeAs('uploads', Str::uuid().'.'.$type, 'local'),
            'file_type' => $type,
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('data.preview', $dataset)], 201);
        }

        return redirect()->route('data.preview', $dataset);
    }

    /**
     * Overview dataset yang sudah diimpor: ringkasan, profil kolom, pratinjau.
     */
    public function show(Dataset $dataset): View
    {
        abort_unless($dataset->isImported(), 404);

        $columns = $dataset->columns()->get();

        return view('data.show', [
            'dataset' => $dataset,
            'columns' => $columns,
            'rows' => $dataset->previewRows($columns->pluck('name')->all()),
            'types' => DatasetColumnController::TYPES,
        ]);
    }

    public function preview(Request $request, Dataset $dataset): View
    {
        abort_unless($dataset->status === 'uploaded', 404);

        $sheets = [];
        $sheet = null;
        $preview = ['headers' => [], 'rows' => []];
        $error = null;

        try {
            $sheets = $this->sheetsOf($dataset);
            $sheet = $request->query('sheet', $dataset->sheet_name ?? $sheets[0] ?? null);

            $preview = DatasetPreview::make(
                ReaderFactory::make($this->pathOf($dataset), $dataset->file_type, $sheet)
            );

            if ($preview['headers'] === []) {
                $error = 'File tidak berisi data.';
            }
        } catch (Exception $e) {
            $error = 'File tidak bisa dibaca: '.$e->getMessage();
        }

        return view('data.preview', compact('dataset', 'sheets', 'sheet', 'preview', 'error'));
    }

    public function confirm(Request $request, Dataset $dataset): RedirectResponse
    {
        abort_unless($dataset->status === 'uploaded', 404);

        $sheets = $this->sheetsOf($dataset);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sheet' => $sheets === [] ? ['prohibited'] : ['required', Rule::in($sheets)],
        ]);

        $dataset->update([
            'name' => $validated['name'],
            'sheet_name' => $validated['sheet'] ?? null,
            'status' => 'pending',
        ]);

        ImportDataset::dispatch($dataset);

        return redirect()->route('data')
            ->with('success', "Dataset \"{$dataset->name}\" masuk antrean import.");
    }

    public function destroy(Dataset $dataset): RedirectResponse
    {
        if ($dataset->status === 'processing') {
            return back()->with('error', 'Dataset sedang diimpor, tunggu sampai selesai.');
        }

        if ($dataset->savedQueries()->exists()) {
            return back()->with('error', 'Dataset tidak bisa dihapus karena masih dipakai saved query.');
        }

        if ($dataset->file_path !== null) {
            Storage::disk('local')->delete($dataset->file_path);
        }

        if ($dataset->table_name !== null) {
            Schema::dropIfExists($dataset->table_name);
        }

        $dataset->delete();

        return redirect()->route('data')->with('success', 'Dataset dihapus.');
    }

    private function pathOf(Dataset $dataset): string
    {
        return Storage::disk('local')->path($dataset->file_path);
    }

    /**
     * @return list<string>
     */
    private function sheetsOf(Dataset $dataset): array
    {
        return $dataset->file_type === 'xlsx'
            ? (new XlsxReader($this->pathOf($dataset)))->sheetNames()
            : [];
    }
}

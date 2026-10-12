<?php

namespace App\Services\Import;

use App\Models\DatasetRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Mengganti isi dataset dengan file baru (Update data) tanpa mengganggu
 * pemakai: data baru diimpor ke tabel sementara, lalu ditukar dengan tabel
 * lama dalam satu transaksi. Bila import gagal, tabel lama tidak tersentuh.
 */
class DatasetUpdater
{
    public function __construct(private DatasetImporter $importer, private DatasetProfiler $profiler) {}

    public function apply(DatasetRevision $revision): void
    {
        $dataset = $revision->dataset;
        $staging = $revision->stagingTable();

        $result = $this->importer->importFile($revision->file_path, $revision->file_type, $revision->sheet_name, $staging);

        /** @var array<string, string> $labels label yang mungkin sudah diedit pengguna */
        $labels = $dataset->columns()->pluck('label', 'name')->all();
        $previousFile = $dataset->file_path;

        DB::transaction(function () use ($dataset, $revision, $staging, $result, $labels) {
            Schema::dropIfExists($dataset->table_name);
            Schema::rename($staging, 'ds_'.$dataset->id);

            $dataset->columns()->delete();
            $dataset->columns()->createMany($this->importer->columnRows($result, $labels));

            // updated_at ikut berubah, sehingga cache query/widget lama tidak dipakai lagi.
            $dataset->update([
                'table_name' => 'ds_'.$dataset->id,
                'original_filename' => $revision->original_filename,
                'file_path' => $revision->file_path,
                'file_type' => $revision->file_type,
                'file_size' => $revision->file_size,
                'sheet_name' => $revision->sheet_name,
                'row_count' => $result['row_count'],
                'column_count' => count($result['names']),
            ]);
        });

        $this->profiler->profile($dataset);

        if ($previousFile !== null && $previousFile !== $revision->file_path) {
            Storage::disk('local')->delete($previousFile);
        }

        $revision->update([
            'status' => 'done',
            'error_message' => null,
            'row_count' => $result['row_count'],
            'columns_added' => array_values(array_diff($result['names'], array_keys($labels))),
            'columns_removed' => array_values(array_diff(array_keys($labels), $result['names'])),
        ]);
    }
}

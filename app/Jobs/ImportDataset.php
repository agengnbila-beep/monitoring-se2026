<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Services\Import\DatasetImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ImportDataset implements ShouldQueue
{
    use Queueable;

    /**
     * Import tidak diulang otomatis: kegagalan biasanya karena isi file,
     * jadi pengguna perlu memperbaiki file lalu upload ulang.
     */
    public int $tries = 1;

    /**
     * Harus lebih kecil dari `retry_after` koneksi queue (DB_QUEUE_RETRY_AFTER),
     * agar job yang masih berjalan tidak diambil worker lain.
     */
    public int $timeout = 600;

    /**
     * Dataset yang dihapus sebelum job berjalan cukup diabaikan.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Dataset $dataset) {}

    public function handle(DatasetImporter $importer): void
    {
        $this->dataset->update(['status' => 'processing', 'error_message' => null]);

        $importer->import($this->dataset);
    }

    public function failed(?Throwable $exception): void
    {
        Schema::dropIfExists('ds_'.$this->dataset->id);

        $this->dataset->columns()->delete();

        $this->dataset->update([
            'status' => 'failed',
            'table_name' => null,
            'row_count' => 0,
            'column_count' => 0,
            'error_message' => Str::limit($exception?->getMessage() ?: 'Import gagal.', 500),
        ]);
    }
}

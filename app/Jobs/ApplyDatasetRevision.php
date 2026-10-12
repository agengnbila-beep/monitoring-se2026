<?php

namespace App\Jobs;

use App\Models\DatasetRevision;
use App\Services\Import\DatasetUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ApplyDatasetRevision implements ShouldQueue
{
    use Queueable;

    /** Sama seperti ImportDataset: kegagalan biasanya karena isi file. */
    public int $tries = 1;

    /** Harus lebih kecil dari DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public DatasetRevision $revision) {}

    public function handle(DatasetUpdater $updater): void
    {
        $this->revision->update(['status' => 'processing', 'error_message' => null]);

        $updater->apply($this->revision);
    }

    /**
     * Data lama tetap dipakai; cukup buang tabel sementara.
     */
    public function failed(?Throwable $exception): void
    {
        Schema::dropIfExists($this->revision->stagingTable());

        $this->revision->update([
            'status' => 'failed',
            'error_message' => Str::limit($exception?->getMessage() ?: 'Pembaruan gagal.', 500),
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pembaruan data untuk dataset yang sudah ada (Update data).
 * Status: uploaded → pending → processing → done / failed.
 */
class DatasetRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_id',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'sheet_name',
        'status',
        'error_message',
        'row_count',
        'columns_added',
        'columns_removed',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'uploaded',
    ];

    protected function casts(): array
    {
        return [
            'columns_added' => 'array',
            'columns_removed' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    /**
     * Nama tabel sementara tempat data baru diimpor sebelum ditukar.
     */
    public function stagingTable(): string
    {
        return "ds_{$this->dataset_id}_rev{$this->id}";
    }
}

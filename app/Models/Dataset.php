<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Dataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'sheet_name',
        'table_name',
        'status',
        'error_message',
        'row_count',
        'column_count',
        'profiled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'profiled_at' => 'datetime',
        ];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(DatasetColumn::class)->orderBy('position');
    }

    public function savedQueries(): HasMany
    {
        return $this->hasMany(SavedQuery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Dataset sudah diimpor dan tabel datanya tersedia.
     */
    public function isImported(): bool
    {
        return $this->status === 'done' && $this->table_name !== null;
    }

    /**
     * Baris pertama tabel data, berurutan sesuai file asli.
     *
     * @param  list<string>  $columns
     * @return Collection<int, object>
     */
    public function previewRows(array $columns, int $limit = 20): Collection
    {
        return DB::table($this->table_name)
            ->orderBy('_row_id')
            ->limit($limit)
            ->get($columns);
    }
}

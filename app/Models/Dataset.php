<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}

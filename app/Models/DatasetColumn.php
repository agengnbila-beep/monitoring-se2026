<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_id',
        'position',
        'name',
        'label',
        'type',
        'null_count',
        'unique_count',
        'min_value',
        'max_value',
    ];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}

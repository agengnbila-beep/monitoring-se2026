<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_id',
        'name',
        'description',
        'config',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

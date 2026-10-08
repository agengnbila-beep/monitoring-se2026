<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueryFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'created_by',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function queries(): HasMany
    {
        return $this->hasMany(SavedQuery::class, 'folder_id')->orderBy('name');
    }

    /**
     * Apakah $folder adalah folder ini sendiri atau salah satu turunannya.
     * Dipakai agar folder tidak bisa dipindah ke dalam dirinya sendiri.
     */
    public function containsOrIs(?self $folder): bool
    {
        for ($current = $folder; $current !== null; $current = $current->parent) {
            if ($current->is($this)) {
                return true;
            }
        }

        return false;
    }
}

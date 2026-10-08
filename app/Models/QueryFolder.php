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

    /**
     * Semua folder sebagai pilihan dropdown, label berupa jalur lengkap
     * ("Monitoring / Per kecamatan"), diurutkan menurut jalur.
     *
     * @return list<array{id: int, label: string}>
     */
    public static function options(): array
    {
        $folders = self::query()->get(['id', 'name', 'parent_id'])->keyBy('id');

        return $folders
            ->map(function (self $folder) use ($folders) {
                $names = [];
                for ($current = $folder; $current !== null; $current = $folders->get($current->parent_id)) {
                    array_unshift($names, $current->name);
                }

                return ['id' => $folder->id, 'label' => implode(' / ', $names)];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Rantai folder dari akar sampai folder ini, untuk breadcrumb.
     *
     * @return list<self>
     */
    public function ancestry(): array
    {
        $chain = [];
        for ($current = $this; $current !== null; $current = $current->parent) {
            array_unshift($chain, $current);
        }

        return $chain;
    }
}

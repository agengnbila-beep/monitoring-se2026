<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedQuery extends Model
{
    use HasFactory;

    public const TYPE_BUILDER = 'builder';

    public const TYPE_SQL = 'sql';

    protected $fillable = [
        'type',
        'sql',
        'folder_id',
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

    /**
     * Default yang sama dengan kolom `type` di database.
     */
    protected $attributes = [
        'type' => self::TYPE_BUILDER,
    ];

    public function isSql(): bool
    {
        return $this->type === self::TYPE_SQL;
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(QueryFolder::class, 'folder_id');
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Widget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'saved_query_id',
        'title',
        'chart_type',
        'options',
        'sort_order',
        'width',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function savedQuery(): BelongsTo
    {
        return $this->belongsTo(SavedQuery::class);
    }
}

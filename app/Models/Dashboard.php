<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'filters',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

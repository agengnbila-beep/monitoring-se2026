<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dataset extends Model
{
    protected $fillable = [
        'name',
        'original_filename',
        'rows',
        'columns',
        'status',
    ];
}

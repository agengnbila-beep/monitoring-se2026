<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dataset extends Model
{
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
}

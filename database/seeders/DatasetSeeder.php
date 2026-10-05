<?php

namespace Database\Seeders;

use App\Models\Dataset;
use Illuminate\Database\Seeder;

class DatasetSeeder extends Seeder
{
    public function run(): void
    {
        Dataset::create([
            'name' => 'Data Penduduk SE2026',
            'original_filename' => 'data-penduduk-se2026.xlsx',
            'file_type' => 'xlsx',
            'row_count' => 10250,
            'column_count' => 12,
            'status' => 'done',
        ]);

        Dataset::create([
            'name' => 'Data Wilayah',
            'original_filename' => 'data-wilayah.xlsx',
            'file_type' => 'xlsx',
            'row_count' => 2450,
            'column_count' => 8,
            'status' => 'done',
        ]);
    }
}

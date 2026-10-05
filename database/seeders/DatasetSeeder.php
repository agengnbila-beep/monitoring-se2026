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
            'rows' => 10250,
            'columns' => 12,
            'status' => 'done',
        ]);

        Dataset::create([
            'name' => 'Data Wilayah',
            'original_filename' => 'data-wilayah.xlsx',
            'rows' => 2450,
            'columns' => 8,
            'status' => 'done',
        ]);
    }
}

<?php

namespace Tests\Concerns;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesImportedDatasets
{
    /**
     * Buat dataset berstatus done beserta tabel datanya, tanpa lewat proses import.
     *
     * @param  array<string, string>  $columns  nama => tipe (text|integer|decimal|date)
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $attributes
     */
    protected function importedDataset(array $columns, array $rows, array $attributes = []): Dataset
    {
        $dataset = Dataset::factory()->create($attributes);
        $table = 'ds_'.$dataset->id;

        Schema::create($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->id('_row_id');

            foreach ($columns as $name => $type) {
                match ($type) {
                    'integer' => $blueprint->integer($name)->nullable(),
                    'decimal' => $blueprint->double($name)->nullable(),
                    default => $blueprint->text($name)->nullable(),
                };
            }
        });

        DB::table($table)->insert($rows);

        $position = 0;
        foreach ($columns as $name => $type) {
            DatasetColumn::factory()->for($dataset)->create([
                'position' => ++$position,
                'name' => $name,
                'label' => ucfirst($name),
                'type' => $type,
            ]);
        }

        $dataset->update([
            'table_name' => $table,
            'row_count' => count($rows),
            'column_count' => count($columns),
        ]);

        return $dataset;
    }
}

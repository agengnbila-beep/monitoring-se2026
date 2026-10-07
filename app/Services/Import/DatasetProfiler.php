<?php

namespace App\Services\Import;

use App\Models\Dataset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatasetProfiler
{
    /**
     * Hitung jumlah null, nilai unik, min, dan max setiap kolom dengan satu
     * kali baca tabel, lalu simpan di `dataset_columns` sebagai cache.
     */
    public function profile(Dataset $dataset): void
    {
        $columns = $dataset->columns()->get();
        $grammar = DB::connection()->getQueryGrammar();

        $selects = ['COUNT(*) AS "row_count"'];
        foreach ($columns as $index => $column) {
            $wrapped = $grammar->wrap($column->name);
            $selects[] = "COUNT(*) - COUNT({$wrapped}) AS \"null_{$index}\"";
            $selects[] = "COUNT(DISTINCT {$wrapped}) AS \"unique_{$index}\"";
            $selects[] = "MIN({$wrapped}) AS \"min_{$index}\"";
            $selects[] = "MAX({$wrapped}) AS \"max_{$index}\"";
        }

        $stats = (array) DB::table($dataset->table_name)->selectRaw(implode(', ', $selects))->first();

        foreach ($columns as $index => $column) {
            $column->update([
                'null_count' => $stats["null_{$index}"],
                'unique_count' => $stats["unique_{$index}"],
                'min_value' => self::shorten($stats["min_{$index}"]),
                'max_value' => self::shorten($stats["max_{$index}"]),
            ]);
        }

        $dataset->update([
            'row_count' => $stats['row_count'],
            'profiled_at' => now(),
        ]);
    }

    private static function shorten(mixed $value): ?string
    {
        return $value === null ? null : Str::limit((string) $value, 250);
    }
}

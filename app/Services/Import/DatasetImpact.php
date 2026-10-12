<?php

namespace App\Services\Import;

use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Models\Widget;
use Illuminate\Support\Arr;

/**
 * Mencari apa saja yang ikut terdampak bila kolom-kolom tertentu hilang
 * dari dataset setelah Update data.
 */
class DatasetImpact
{
    /**
     * @param  list<string>  $removed  nama kolom yang akan hilang
     * @return array{filters: list<string>, widgets: list<string>, sql_files: list<string>}
     */
    public static function ofRemovedColumns(Dataset $dataset, array $removed): array
    {
        if ($removed === []) {
            return ['filters' => [], 'widgets' => [], 'sql_files' => []];
        }

        $affected = $dataset->savedQueries()
            ->where('type', SavedQuery::TYPE_BUILDER)
            ->get()
            ->filter(fn (SavedQuery $query) => array_intersect(self::columnsUsedBy($query), $removed) !== []);

        $pattern = '/\b('.implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $removed)).')\b/i';
        $sqlFiles = SavedQuery::where('type', SavedQuery::TYPE_SQL)
            ->where('sql', 'like', '%ds_'.$dataset->id.'%')
            ->get()
            ->filter(fn (SavedQuery $file) => preg_match('/\bds_'.$dataset->id.'\b/', $file->sql) === 1 && preg_match($pattern, $file->sql) === 1);

        $widgets = Widget::with('dashboard:id,name')
            ->whereIn('saved_query_id', [...$affected->modelKeys(), ...$sqlFiles->modelKeys()])
            ->get()
            ->map(fn (Widget $widget) => "{$widget->title} ({$widget->dashboard->name})");

        return [
            'filters' => $affected->pluck('name')->values()->all(),
            'widgets' => $widgets->values()->all(),
            'sql_files' => $sqlFiles->pluck('name')->values()->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function columnsUsedBy(SavedQuery $query): array
    {
        $config = $query->config ?? [];

        return array_values(array_filter([
            ...($config['select'] ?? []),
            ...($config['group_by'] ?? []),
            ...array_column($config['aggregates'] ?? [], 'column'),
            ...array_column($config['filters'] ?? [], 'column'),
            ...array_column($config['order_by'] ?? [], 'column'),
            ...Arr::flatten((array) ($query->visualization['options'] ?? [])),
        ], 'is_string'));
    }
}

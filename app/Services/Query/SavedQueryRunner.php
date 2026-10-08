<?php

namespace App\Services\Query;

use App\Models\SavedQuery;
use Illuminate\Validation\ValidationException;

/**
 * Satu pintu untuk menjalankan saved query apa pun jenisnya:
 * `builder` (Filter Data) lewat QueryEngine, `sql` lewat SqlRunner.
 */
class SavedQueryRunner
{
    public function __construct(private QueryEngine $engine, private SqlRunner $sql) {}

    /**
     * @param  list<array{column: string, op: string, value: mixed}>  $filters  filter tambahan (filter global dashboard)
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public function run(SavedQuery $query, array $filters = []): array
    {
        if ($query->isSql()) {
            return $this->sql->cachedRun($query->sql, SqlRunner::DEFAULT_LIMIT, $filters);
        }

        $config = $query->config;
        $config['filters'] = [...($config['filters'] ?? []), ...$filters];

        return $this->engine->cachedRun($query->dataset, $config);
    }

    /**
     * Kolom yang bisa difilter: untuk Filter Data semua kolom dataset,
     * untuk SQL kolom hasil query (filter dibungkus di luar SQL).
     *
     * @return list<string>
     */
    public function filterableColumns(SavedQuery $query): array
    {
        return $query->isSql()
            ? $this->sql->columnNames($query->sql)
            : $query->dataset->columns()->pluck('name')->all();
    }

    /**
     * Kolom hasil, dipakai untuk memvalidasi opsi widget.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function outputColumns(SavedQuery $query): array
    {
        return $query->isSql()
            ? $this->sql->columnNames($query->sql)
            : $this->engine->outputColumnNames($query->dataset, $query->config);
    }
}

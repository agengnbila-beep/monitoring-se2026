<?php

namespace App\Services\Query;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

class QueryEngine
{
    public const DEFAULT_LIMIT = 1000;

    public const MAX_LIMIT = 10000;

    public const FUNCTIONS = ['count', 'sum', 'avg', 'min', 'max'];

    public const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like', 'between', 'is_null', 'not_null'];

    /** Query lebih lama dari ini dicatat di log untuk dievaluasi. */
    private const SLOW_QUERY_MS = 2000;

    /**
     * Validasi konfigurasi builder, jalankan sebagai SQL, dan kembalikan
     * hasil dengan format docs/api-contract.md bagian 5.
     *
     * @param  array<string, mixed>  $config
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array{row_count: int, limit: int, truncated: bool, duration_ms: int}}
     *
     * @throws ValidationException
     */
    public function run(Dataset $dataset, array $config): array
    {
        abort_unless($dataset->isImported(), 409, 'Dataset belum selesai diimpor.');

        /** @var Collection<string, DatasetColumn> $columns */
        $columns = $dataset->columns()->get()->keyBy('name');
        $config = $this->validate($config, $columns);

        $started = microtime(true);
        $rows = $this->build($dataset, $config)->limit($config['limit'] + 1)->get();
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        if ($durationMs > self::SLOW_QUERY_MS) {
            Log::warning('Query lambat', ['dataset_id' => $dataset->id, 'duration_ms' => $durationMs, 'config' => $config]);
        }

        $truncated = $rows->count() > $config['limit'];
        $rows = $rows->take($config['limit']);

        return [
            'columns' => $this->outputColumns($config, $columns),
            'rows' => $rows->map(fn (object $row) => (array) $row)->values()->all(),
            'meta' => [
                'row_count' => $rows->count(),
                'limit' => $config['limit'],
                'truncated' => $truncated,
                'duration_ms' => $durationMs,
            ],
        ];
    }

    /**
     * Nama kolom tidak bisa memakai parameter binding, jadi setiap nama kolom
     * wajib ada di whitelist `dataset_columns`. Nilai filter selalu di-binding.
     *
     * @param  array<string, mixed>  $config
     * @param  Collection<string, DatasetColumn>  $columns
     * @return array{select: list<string>, aggregates: list<array{fn: string, column: ?string, as: string}>, filters: list<array{column: string, op: string, value?: mixed}>, group_by: list<string>, order_by: list<array{column: string, dir: string}>, limit: int}
     *
     * @throws ValidationException
     */
    public function validate(array $config, Collection $columns): array
    {
        $names = $columns->keys()->all();
        $numeric = $columns->filter(fn (DatasetColumn $column) => in_array($column->type, ['integer', 'decimal'], true))->keys()->all();

        $validator = Validator::make($config, [
            'select' => ['sometimes', 'array', 'max:50'],
            'select.*' => ['string', Rule::in($names)],
            'aggregates' => ['sometimes', 'array', 'max:10'],
            'aggregates.*.fn' => ['required', Rule::in(self::FUNCTIONS)],
            'aggregates.*.column' => ['nullable', 'string', Rule::in($names)],
            'aggregates.*.as' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::notIn($names), 'distinct'],
            'filters' => ['sometimes', 'array', 'max:20'],
            'filters.*.column' => ['required', 'string', Rule::in($names)],
            'filters.*.op' => ['required', Rule::in(self::OPERATORS)],
            'group_by' => ['sometimes', 'array', 'max:10'],
            'group_by.*' => ['string', Rule::in($names)],
            'order_by' => ['sometimes', 'array', 'max:5'],
            'order_by.*.column' => ['required', 'string'],
            'order_by.*.dir' => ['sometimes', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ], [
            '*.in' => 'Nilai :input tidak dikenal.',
            'select.*.in' => 'Kolom \':input\' tidak ada di dataset ini.',
            'aggregates.*.column.in' => 'Kolom \':input\' tidak ada di dataset ini.',
            'filters.*.column.in' => 'Kolom \':input\' tidak ada di dataset ini.',
            'group_by.*.in' => 'Kolom \':input\' tidak ada di dataset ini.',
            'aggregates.*.fn.in' => 'Fungsi agregat \':input\' tidak didukung.',
            'filters.*.op.in' => 'Operator \':input\' tidak didukung.',
            'aggregates.*.as.regex' => 'Alias hanya boleh huruf kecil, angka, dan garis bawah, diawali huruf.',
            'aggregates.*.as.not_in' => 'Alias \':input\' sama dengan nama kolom.',
            'aggregates.*.as.distinct' => 'Alias \':input\' dipakai lebih dari sekali.',
            'limit.max' => 'Limit maksimal '.self::MAX_LIMIT.' baris.',
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($config, $names, $numeric) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateAggregates($validator, $config['aggregates'] ?? [], $numeric);
            $this->validateFilters($validator, $config['filters'] ?? []);
            $this->validateGrouping($validator, $config, $names);
        });

        $validated = $validator->validate();

        return [
            'select' => array_values($validated['select'] ?? []),
            'aggregates' => array_map(fn (array $aggregate) => [
                'fn' => $aggregate['fn'],
                'column' => $aggregate['column'] ?? null,
                'as' => $aggregate['as'],
            ], array_values($validated['aggregates'] ?? [])),
            'filters' => array_values($config['filters'] ?? []),
            'group_by' => array_values($validated['group_by'] ?? []),
            'order_by' => array_map(fn (array $order) => [
                'column' => $order['column'],
                'dir' => $order['dir'] ?? 'asc',
            ], array_values($validated['order_by'] ?? [])),
            'limit' => (int) ($validated['limit'] ?? self::DEFAULT_LIMIT),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $aggregates
     * @param  list<string>  $numeric
     */
    private function validateAggregates(ValidatorInstance $validator, array $aggregates, array $numeric): void
    {
        foreach ($aggregates as $index => $aggregate) {
            $column = $aggregate['column'] ?? null;

            if ($column === null && $aggregate['fn'] !== 'count') {
                $validator->errors()->add("aggregates.{$index}.column", "Fungsi {$aggregate['fn']} membutuhkan kolom.");
            }

            if (in_array($aggregate['fn'], ['sum', 'avg'], true) && $column !== null && ! in_array($column, $numeric, true)) {
                $validator->errors()->add("aggregates.{$index}.column", "Fungsi {$aggregate['fn']} hanya untuk kolom angka, '{$column}' bukan angka.");
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $filters
     */
    private function validateFilters(ValidatorInstance $validator, array $filters): void
    {
        foreach ($filters as $index => $filter) {
            $key = "filters.{$index}.value";
            $value = $filter['value'] ?? null;

            $error = match ($filter['op']) {
                'is_null', 'not_null' => null,
                'in', 'not_in' => is_array($value) && $value !== [] && count($value) <= 100 && self::allScalar($value)
                    ? null : 'Operator in/not_in membutuhkan daftar 1–100 nilai.',
                'between' => is_array($value) && count($value) === 2 && self::allScalar($value)
                    ? null : 'Operator between membutuhkan tepat 2 nilai.',
                'like' => is_string($value) && $value !== ''
                    ? null : 'Operator like membutuhkan teks.',
                default => is_scalar($value)
                    ? null : 'Filter membutuhkan satu nilai.',
            };

            if ($error !== null) {
                $validator->errors()->add($key, $error);
            }
        }
    }

    /**
     * Saat ada agregat atau group_by, kolom biasa di select wajib ikut di group_by;
     * tanpa aturan ini SQLite mengambil nilai acak dari setiap grup.
     * Urutan hanya boleh memakai kolom yang muncul di hasil query.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $names
     */
    private function validateGrouping(ValidatorInstance $validator, array $config, array $names): void
    {
        $select = $config['select'] ?? [];
        $aliases = array_column($config['aggregates'] ?? [], 'as');
        $grouped = $aliases !== [] || ($config['group_by'] ?? []) !== [];

        if ($grouped) {
            foreach ($select as $index => $column) {
                if (! in_array($column, $config['group_by'] ?? [], true)) {
                    $validator->errors()->add("select.{$index}", "Kolom '{$column}' harus ada di group_by karena query memakai agregat.");
                }
            }
        }

        $outputColumns = $grouped || $select !== [] ? [...$select, ...$aliases] : $names;

        foreach ($config['order_by'] ?? [] as $index => $order) {
            if (! in_array($order['column'], $outputColumns, true)) {
                $validator->errors()->add("order_by.{$index}.column", "Urutan '{$order['column']}' harus berupa kolom hasil query.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function build(Dataset $dataset, array $config): Builder
    {
        $query = DB::table($dataset->table_name);
        $grammar = $query->getGrammar();

        if ($config['select'] === [] && $config['aggregates'] === []) {
            $query->select($dataset->columns()->pluck('name')->all());
        } else {
            $query->select($config['select']);
        }

        foreach ($config['aggregates'] as $aggregate) {
            $target = $aggregate['column'] === null ? '*' : $grammar->wrap($aggregate['column']);
            $query->selectRaw(strtoupper($aggregate['fn'])."({$target}) AS ".$grammar->wrap($aggregate['as']));
        }

        foreach ($config['filters'] as $filter) {
            $column = $filter['column'];
            $value = $filter['value'] ?? null;

            match ($filter['op']) {
                'in' => $query->whereIn($column, $value),
                'not_in' => $query->whereNotIn($column, $value),
                'between' => $query->whereBetween($column, $value),
                'is_null' => $query->whereNull($column),
                'not_null' => $query->whereNotNull($column),
                'like' => $query->whereRaw($grammar->wrap($column)." LIKE ? ESCAPE '\\'", ['%'.addcslashes($value, '%_\\').'%']),
                default => $query->where($column, $filter['op'], $value),
            };
        }

        if ($config['group_by'] !== []) {
            $query->groupBy($config['group_by']);
        }

        foreach ($config['order_by'] as $order) {
            $query->orderBy($order['column'], $order['dir']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  Collection<string, DatasetColumn>  $columns
     * @return list<array{name: string, label: string, type: string}>
     */
    private function outputColumns(array $config, Collection $columns): array
    {
        $selected = $config['select'] === [] && $config['aggregates'] === []
            ? $columns->keys()->all()
            : $config['select'];

        $output = array_map(fn (string $name) => [
            'name' => $name,
            'label' => $columns[$name]->label,
            'type' => $columns[$name]->type,
        ], $selected);

        foreach ($config['aggregates'] as $aggregate) {
            $type = match ($aggregate['fn']) {
                'count' => 'integer',
                'avg' => 'decimal',
                default => $columns[$aggregate['column']]->type,
            };

            $output[] = [
                'name' => $aggregate['as'],
                'label' => $aggregate['as'],
                'type' => $type,
            ];
        }

        return $output;
    }

    /**
     * @param  array<mixed>  $values
     */
    private static function allScalar(array $values): bool
    {
        return collect($values)->every(fn (mixed $value) => is_scalar($value));
    }
}

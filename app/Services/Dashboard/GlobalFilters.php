<?php

namespace App\Services\Dashboard;

use App\Models\Dashboard;
use App\Models\Dataset;
use Illuminate\Support\Facades\Validator;

class GlobalFilters
{
    /**
     * Ubah nilai filter dashboard dari request menjadi filter query engine
     * untuk satu dataset. Filter hanya berlaku bila dataset punya kolom
     * bernama sama dengan `key`; filter lain diabaikan untuk widget ini.
     *
     * @param  array<string, mixed>  $input  isi parameter `filters` dari request
     * @return array{filters: list<array{column: string, op: string, value: mixed}>, applied: list<string>, ignored: list<string>}
     */
    public static function forDataset(Dashboard $dashboard, Dataset $dataset, array $input): array
    {
        $definitions = collect($dashboard->filters ?? [])->keyBy('key');
        $values = self::validate($definitions->all(), $input);
        $columns = $dataset->columns()->pluck('name')->all();

        $result = ['filters' => [], 'applied' => [], 'ignored' => []];

        foreach ($values as $key => $value) {
            if (! in_array($key, $columns, true)) {
                $result['ignored'][] = $key;

                continue;
            }

            $result['applied'][] = $key;

            if ($definitions[$key]['type'] === 'select') {
                $result['filters'][] = ['column' => $key, 'op' => '=', 'value' => $value];

                continue;
            }

            $from = $value['from'] ?? null;
            $to = $value['to'] ?? null;

            $result['filters'][] = match (true) {
                $from !== null && $to !== null => ['column' => $key, 'op' => 'between', 'value' => [$from, $to.' 23:59:59']],
                $from !== null => ['column' => $key, 'op' => '>=', 'value' => $from],
                default => ['column' => $key, 'op' => '<=', 'value' => $to.' 23:59:59'],
            };
        }

        return $result;
    }

    /**
     * Hanya key yang didefinisikan dashboard yang dipakai; nilai kosong dibuang.
     *
     * @param  array<string, array{key: string, label: string, type: string}>  $definitions
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function validate(array $definitions, array $input): array
    {
        $rules = [];
        foreach ($definitions as $key => $definition) {
            if ($definition['type'] === 'select') {
                $rules[$key] = ['nullable', 'string', 'max:255'];
            } else {
                $rules[$key] = ['nullable', 'array:from,to'];
                $rules["{$key}.from"] = ['nullable', 'date_format:Y-m-d'];
                $rules["{$key}.to"] = ['nullable', 'date_format:Y-m-d', "after_or_equal:{$key}.from"];
            }
        }

        $validated = Validator::make($input, $rules, [
            '*.from.date_format' => 'Tanggal awal harus berformat YYYY-MM-DD.',
            '*.to.date_format' => 'Tanggal akhir harus berformat YYYY-MM-DD.',
            '*.to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ])->validate();

        return collect($validated)
            ->map(fn (mixed $value) => is_array($value) ? array_filter($value, fn ($v) => $v !== null && $v !== '') : $value)
            ->filter(fn (mixed $value) => $value !== null && $value !== '' && $value !== [])
            ->all();
    }
}

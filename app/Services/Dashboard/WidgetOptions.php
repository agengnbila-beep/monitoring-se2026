<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WidgetOptions
{
    public const CHART_TYPES = ['kpi', 'bar', 'line', 'pie', 'table'];

    /**
     * Validasi `options` sesuai jenis chart (docs/api-contract.md bagian 6).
     * Setiap nama kolom wajib ada di hasil saved query; key lain dibuang.
     *
     * @param  array<string, mixed>  $options
     * @param  list<string>  $columns  nama kolom hasil saved query
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(string $chartType, array $options, array $columns): array
    {
        $column = ['required', 'string', Rule::in($columns)];

        $rules = match ($chartType) {
            'kpi' => [
                'value' => $column,
                'format' => ['sometimes', Rule::in(['number', 'percent'])],
            ],
            'bar', 'line' => [
                'x' => $column,
                'y' => ['required', 'array', 'min:1', 'max:5'],
                'y.*' => $column,
                'stacked' => ['sometimes', 'boolean'],
            ],
            'pie' => [
                'label' => $column,
                'value' => $column,
            ],
            'table' => [
                'columns' => ['sometimes', 'array', 'min:1'],
                'columns.*' => $column,
            ],
        };

        try {
            $validated = Validator::make($options, $rules, [
                '*.in' => 'Kolom \':input\' tidak ada di hasil query.',
                'required' => 'Opsi :attribute wajib diisi untuk chart '.$chartType.'.',
            ])->validate();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ["options.{$key}" => $messages])->all()
            );
        }

        return match ($chartType) {
            'kpi' => ['value' => $validated['value'], 'format' => $validated['format'] ?? 'number'],
            'bar', 'line' => ['x' => $validated['x'], 'y' => array_values($validated['y']), 'stacked' => (bool) ($validated['stacked'] ?? false)],
            'pie' => ['label' => $validated['label'], 'value' => $validated['value']],
            'table' => ['columns' => array_values($validated['columns'] ?? $columns)],
        };
    }
}

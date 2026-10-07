<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Services\Import\DatasetProfiler;
use App\Services\Import\TypeDetector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DatasetColumnController extends Controller
{
    public const TYPES = ['text', 'integer', 'decimal', 'date'];

    /**
     * Simpan label dan tipe kolom. Perubahan tipe ikut mengubah tipe kolom
     * di tabel data agar urutan dan perhitungan memakai tipe yang benar.
     */
    public function update(Request $request, Dataset $dataset, DatasetProfiler $profiler): RedirectResponse
    {
        abort_unless($dataset->isImported(), 404);

        $validated = $request->validate([
            'columns' => ['required', 'array'],
            'columns.*.label' => ['required', 'string', 'max:255'],
            'columns.*.type' => ['required', Rule::in(self::TYPES)],
        ], [
            'columns.*.label.required' => 'Label kolom tidak boleh kosong.',
            'columns.*.type.in' => 'Tipe kolom tidak dikenal.',
        ]);

        /** @var Collection<int, DatasetColumn> $columns */
        $columns = $dataset->columns()->get()->filter(
            fn (DatasetColumn $column) => isset($validated['columns'][$column->id])
        );

        $retyped = $columns->filter(
            fn (DatasetColumn $column) => $validated['columns'][$column->id]['type'] !== $column->type
        );

        $this->ensureValuesFitNewTypes($dataset, $retyped, $validated['columns']);

        DB::transaction(function () use ($dataset, $columns, $retyped, $validated) {
            if ($retyped->isNotEmpty()) {
                Schema::table($dataset->table_name, function (Blueprint $table) use ($retyped, $validated) {
                    foreach ($retyped as $column) {
                        match ($validated['columns'][$column->id]['type']) {
                            'integer' => $table->integer($column->name)->nullable()->change(),
                            'decimal' => $table->double($column->name)->nullable()->change(),
                            default => $table->text($column->name)->nullable()->change(),
                        };
                    }
                });
            }

            foreach ($columns as $column) {
                $column->update($validated['columns'][$column->id]);
            }
        });

        if ($retyped->isNotEmpty()) {
            $profiler->profile($dataset);
        }

        return redirect()->route('data.show', $dataset)->with('success', 'Perubahan kolom disimpan.');
    }

    /**
     * Tolak perubahan tipe bila ada nilai yang tidak cocok, misalnya "N/A"
     * pada kolom yang akan dijadikan integer.
     *
     * @param  Collection<int, DatasetColumn>  $retyped
     * @param  array<int, array{label: string, type: string}>  $input
     */
    private function ensureValuesFitNewTypes(Dataset $dataset, Collection $retyped, array $input): void
    {
        $errors = [];

        foreach ($retyped as $column) {
            $type = $input[$column->id]['type'];

            if ($type === 'text') {
                continue;
            }

            $values = DB::table($dataset->table_name)
                ->whereNotNull($column->name)
                ->distinct()
                ->orderBy($column->name)
                ->cursor()
                ->map(fn (object $row) => $row->{$column->name});

            foreach ($values as $value) {
                if (! TypeDetector::matches($type, $value)) {
                    $errors["columns.{$column->id}.type"] = sprintf(
                        'Kolom "%s" tidak bisa dijadikan %s karena berisi nilai "%s".',
                        $column->label,
                        $type,
                        Str::limit((string) $value, 40),
                    );

                    break;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

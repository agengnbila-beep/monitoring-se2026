<?php

namespace App\Services\Import;

use App\Models\Dataset;
use Generator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatasetImporter
{
    /** Batas variabel per query SQLite adalah 32.766; sisakan ruang. */
    private const MAX_BINDINGS = 30000;

    private const MAX_CHUNK_ROWS = 500;

    /**
     * Impor file dataset ke tabel `ds_{id}` dalam dua tahap:
     * tahap 1 membaca seluruh file untuk menentukan tipe kolom,
     * tahap 2 membaca ulang dan memasukkan data per chunk.
     */
    public function import(Dataset $dataset): void
    {
        $table = 'ds_'.$dataset->id;
        $result = $this->importFile($dataset->file_path, $dataset->file_type, $dataset->sheet_name, $table);

        $dataset->columns()->delete();
        $dataset->columns()->createMany($this->columnRows($result));

        $dataset->update([
            'table_name' => $table,
            'row_count' => $result['row_count'],
            'column_count' => count($result['names']),
        ]);
    }

    /**
     * Impor satu file (di disk `local`) ke tabel baru bernama $table.
     * Dipakai juga untuk pembaruan data, yang mengimpor ke tabel sementara
     * lebih dulu sebelum ditukar dengan tabel lama.
     *
     * @return array{headers: list<string>, names: list<string>, types: list<string>, row_count: int}
     */
    public function importFile(string $path, string $type, ?string $sheet, string $table): array
    {
        $rows = fn () => ReaderFactory::make(Storage::disk('local')->path($path), $type, $sheet)->rows();

        [$headers, $types] = $this->analyze($rows());
        $names = ColumnNamer::sanitize($headers);

        $this->createTable($table, $names, $types);

        return [
            'headers' => $headers,
            'names' => $names,
            'types' => $types,
            'row_count' => $this->insertRows($rows(), $table, $names, $types),
        ];
    }

    /**
     * Baris `dataset_columns` dari hasil import. Label yang sudah diubah
     * pengguna untuk kolom yang sama dipertahankan ($labels: name => label).
     *
     * @param  array{headers: list<string>, names: list<string>, types: list<string>, row_count: int}  $result
     * @param  array<string, string>  $labels
     * @return list<array{position: int, name: string, label: string, type: string}>
     */
    public function columnRows(array $result, array $labels = []): array
    {
        return array_map(fn (int $index) => [
            'position' => $index + 1,
            'name' => $result['names'][$index],
            'label' => $labels[$result['names'][$index]]
                ?? ($result['headers'][$index] !== '' ? $result['headers'][$index] : $result['names'][$index]),
            'type' => $result['types'][$index],
        ], array_keys($result['names']));
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function analyze(Generator $rows): array
    {
        if (! $rows->valid()) {
            throw new RuntimeException('File tidak berisi data.');
        }

        $headers = array_map(fn (mixed $header) => trim((string) $header), $rows->current());
        $detector = new TypeDetector;

        for ($rows->next(); $rows->valid(); $rows->next()) {
            $detector->observe(array_slice($rows->current(), 0, count($headers)));
        }

        return [$headers, $detector->types(count($headers))];
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $types
     */
    private function createTable(string $table, array $names, array $types): void
    {
        Schema::dropIfExists($table);

        Schema::create($table, function (Blueprint $blueprint) use ($names, $types) {
            $blueprint->id('_row_id');

            foreach ($names as $index => $name) {
                match ($types[$index]) {
                    'integer' => $blueprint->integer($name)->nullable(),
                    'decimal' => $blueprint->double($name)->nullable(),
                    default => $blueprint->text($name)->nullable(),
                };
            }
        });
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $types
     */
    private function insertRows(Generator $rows, string $table, array $names, array $types): int
    {
        $chunkSize = max(1, min(self::MAX_CHUNK_ROWS, intdiv(self::MAX_BINDINGS, count($names))));
        $columnCount = count($names);
        $rowCount = 0;
        $buffer = [];

        for ($rows->next(); $rows->valid(); $rows->next()) {
            $values = array_pad(array_slice($rows->current(), 0, $columnCount), $columnCount, null);

            $buffer[] = array_combine($names, array_map(
                fn (mixed $value, string $type) => self::cast($value, $type),
                $values,
                $types,
            ));

            if (count($buffer) === $chunkSize) {
                $rowCount += $this->flush($table, $buffer);
            }
        }

        return $rowCount + $this->flush($table, $buffer);
    }

    /**
     * Setiap chunk punya transaksi sendiri agar penulisan lain
     * (mis. session login) tidak menunggu sampai seluruh import selesai.
     *
     * @param  list<array<string, mixed>>  $buffer
     */
    private function flush(string $table, array &$buffer): int
    {
        if ($buffer === []) {
            return 0;
        }

        DB::transaction(fn () => DB::table($table)->insert($buffer));

        $count = count($buffer);
        $buffer = [];

        return $count;
    }

    private static function cast(mixed $value, string $type): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            default => is_bool($value) ? (string) (int) $value : (string) $value,
        };
    }
}

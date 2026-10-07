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
        [$headers, $types] = $this->analyze($dataset);

        $names = ColumnNamer::sanitize($headers);
        $table = 'ds_'.$dataset->id;

        $this->createTable($table, $names, $types);

        $dataset->columns()->delete();
        $dataset->columns()->createMany(array_map(fn (int $index) => [
            'position' => $index + 1,
            'name' => $names[$index],
            'label' => $headers[$index] !== '' ? $headers[$index] : $names[$index],
            'type' => $types[$index],
        ], array_keys($names)));

        $rowCount = $this->insertRows($dataset, $table, $names, $types);

        $dataset->update([
            'table_name' => $table,
            'row_count' => $rowCount,
            'column_count' => count($names),
        ]);
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function analyze(Dataset $dataset): array
    {
        $rows = $this->rows($dataset);

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
    private function insertRows(Dataset $dataset, string $table, array $names, array $types): int
    {
        $chunkSize = max(1, min(self::MAX_CHUNK_ROWS, intdiv(self::MAX_BINDINGS, count($names))));
        $columnCount = count($names);
        $rowCount = 0;
        $buffer = [];

        $rows = $this->rows($dataset);

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

    private function rows(Dataset $dataset): Generator
    {
        return ReaderFactory::make(
            Storage::disk('local')->path($dataset->file_path),
            $dataset->file_type,
            $dataset->sheet_name,
        )->rows();
    }
}

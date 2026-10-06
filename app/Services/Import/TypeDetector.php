<?php

namespace App\Services\Import;

class TypeDetector
{
    /** Urutan dari yang paling spesifik. */
    private const TYPES = ['integer', 'decimal', 'date'];

    /** @var array<int, array<string, true>> kandidat tipe per kolom */
    private array $candidates = [];

    /**
     * Amati satu baris data; tipe yang tidak cocok dicoret dari kandidat.
     *
     * @param  list<mixed>  $row
     */
    public function observe(array $row): void
    {
        foreach ($row as $index => $value) {
            if ($value === null) {
                continue;
            }

            $this->candidates[$index] ??= array_fill_keys(self::TYPES, true);

            foreach (array_keys($this->candidates[$index]) as $type) {
                if (! self::matches($type, $value)) {
                    unset($this->candidates[$index][$type]);
                }
            }
        }
    }

    /**
     * Tipe akhir per kolom. Kolom yang selalu kosong menjadi text.
     *
     * @return list<string>
     */
    public function types(int $columnCount): array
    {
        $types = [];

        for ($index = 0; $index < $columnCount; $index++) {
            $types[] = array_key_first($this->candidates[$index] ?? []) ?? 'text';
        }

        return $types;
    }

    public static function matches(string $type, mixed $value): bool
    {
        return match ($type) {
            'integer' => self::isInteger($value),
            'decimal' => self::isInteger($value) || is_float($value)
                || (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value) === 1),
            'date' => is_string($value)
                && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) === 1
                && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)),
        };
    }

    /**
     * Angka bulat tanpa nol di depan dan maksimal 15 digit.
     * Kode seperti "0101" tetap text agar nol di depan tidak hilang;
     * angka > 15 digit (NIK, kode wilayah) tetap text karena JavaScript
     * tidak bisa menampilkannya dengan presisi penuh.
     */
    private static function isInteger(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value)) {
            return abs($value) < 10 ** 15;
        }

        return is_string($value) && preg_match('/^-?(0|[1-9]\d{0,14})$/', $value) === 1;
    }
}

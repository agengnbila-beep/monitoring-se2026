<?php

namespace App\Services\Import;

use InvalidArgumentException;

class ReaderFactory
{
    public static function make(string $path, string $type, ?string $sheet = null): RowReader
    {
        return match ($type) {
            'csv' => new CsvReader($path),
            'xlsx' => new XlsxReader($path, $sheet),
            'json' => new JsonReader($path),
            default => throw new InvalidArgumentException("Tipe file '{$type}' tidak didukung."),
        };
    }
}

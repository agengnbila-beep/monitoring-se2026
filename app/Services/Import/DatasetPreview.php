<?php

namespace App\Services\Import;

use Illuminate\Support\LazyCollection;

class DatasetPreview
{
    /**
     * Ambil header dan beberapa baris pertama tanpa membaca seluruh file.
     *
     * @return array{headers: list<string>, rows: list<list<mixed>>}
     */
    public static function make(RowReader $reader, int $limit = 20): array
    {
        $rows = LazyCollection::make(fn () => yield from $reader->rows())
            ->take($limit + 1)
            ->values()
            ->all();

        $headers = array_map(fn ($header) => (string) $header, array_shift($rows) ?? []);

        return ['headers' => $headers, 'rows' => $rows];
    }
}

<?php

namespace App\Services\Import;

use Generator;

interface RowReader
{
    /**
     * Membaca file baris demi baris. Baris pertama adalah header.
     *
     * @return Generator<int, list<string|int|float|bool|null>>
     */
    public function rows(): Generator;
}

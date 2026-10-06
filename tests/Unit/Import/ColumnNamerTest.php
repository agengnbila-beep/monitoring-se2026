<?php

namespace Tests\Unit\Import;

use App\Services\Import\ColumnNamer;
use PHPUnit\Framework\TestCase;

class ColumnNamerTest extends TestCase
{
    public function test_headers_become_safe_snake_case_names(): void
    {
        $this->assertSame(
            ['nama_krt', 'jumlah_rp', 'kecamatan'],
            ColumnNamer::sanitize(['Nama KRT', 'Jumlah (Rp)', '  Kecamatan  ']),
        );
    }

    public function test_accents_are_transliterated(): void
    {
        $this->assertSame(['cafe'], ColumnNamer::sanitize(['Café']));
    }

    public function test_empty_headers_get_position_based_names(): void
    {
        $this->assertSame(['nama', 'kolom_2', 'kolom_3'], ColumnNamer::sanitize(['nama', '', null]));
    }

    public function test_duplicates_get_numbered_suffix(): void
    {
        $this->assertSame(['status', 'status_2', 'status_3'], ColumnNamer::sanitize(['Status', 'status', 'STATUS']));
    }

    public function test_names_never_start_with_underscore_or_digit(): void
    {
        $this->assertSame(['row_id', 'k_2026'], ColumnNamer::sanitize(['_row_id', '2026']));
    }

    public function test_sql_injection_attempt_is_neutralised(): void
    {
        $this->assertSame(['nama_drop_table_users'], ColumnNamer::sanitize(['nama"; DROP TABLE users; --']));
    }

    public function test_long_names_are_truncated(): void
    {
        $this->assertSame(60, strlen(ColumnNamer::sanitize([str_repeat('a', 100)])[0]));
    }
}

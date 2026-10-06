<?php

namespace Tests\Unit\Import;

use App\Services\Import\TypeDetector;
use PHPUnit\Framework\TestCase;

class TypeDetectorTest extends TestCase
{
    public function test_detects_each_type(): void
    {
        $this->assertSame(
            ['integer', 'decimal', 'date', 'text'],
            $this->detect([
                ['20', '3.5', '2026-06-01', 'Budi'],
                ['31', '10', '2026-06-02 08:30:00', 'Siti'],
            ]),
        );
    }

    public function test_one_non_matching_value_downgrades_the_column(): void
    {
        $this->assertSame(['text'], $this->detect([['20'], ['31'], ['N/A']]));
    }

    public function test_nulls_are_ignored_and_empty_columns_are_text(): void
    {
        $this->assertSame(['integer', 'text'], $this->detect([['20', null], [null, null], ['5', null]]));
    }

    public function test_leading_zeros_and_long_codes_stay_text(): void
    {
        $this->assertSame(['text', 'text'], $this->detect([['0101', '1504010013000200']]));
    }

    public function test_native_numbers_from_xlsx_and_json(): void
    {
        $this->assertSame(['integer', 'decimal', 'integer'], $this->detect([[20, 3.5, true], [31, 4, false]]));
    }

    public function test_invalid_calendar_date_is_text(): void
    {
        $this->assertSame(['text'], $this->detect([['2026-02-30']]));
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<string>
     */
    private function detect(array $rows): array
    {
        $detector = new TypeDetector;

        foreach ($rows as $row) {
            $detector->observe($row);
        }

        return $detector->types(count($rows[0]));
    }
}

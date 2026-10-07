<?php

namespace Tests\Feature\Services\Query;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\User;
use App\Services\Query\QueryEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueryEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_per_group_sorted_descending(): void
    {
        $result = $this->runQuery([
            'select' => ['kecamatan'],
            'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']],
            'group_by' => ['kecamatan'],
            'order_by' => [['column' => 'jumlah', 'dir' => 'desc']],
        ]);

        $this->assertSame(
            [['kecamatan' => 'Bangli', 'jumlah' => 3], ['kecamatan' => 'Susut', 'jumlah' => 2]],
            $result['rows'],
        );
        $this->assertSame(
            [
                ['name' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'text'],
                ['name' => 'jumlah', 'label' => 'jumlah', 'type' => 'integer'],
            ],
            $result['columns'],
        );
    }

    public function test_sum_and_avg_on_numeric_column(): void
    {
        $result = $this->runQuery([
            'aggregates' => [
                ['fn' => 'sum', 'column' => 'art', 'as' => 'total_art'],
                ['fn' => 'avg', 'column' => 'luas', 'as' => 'rata_luas'],
            ],
        ]);

        $this->assertSame([['total_art' => 20, 'rata_luas' => 2.0]], $result['rows']);
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  list<string>  $expectedNames
     */
    #[DataProvider('filters')]
    public function test_filter_operators(array $filter, array $expectedNames): void
    {
        $result = $this->runQuery([
            'select' => ['nama'],
            'filters' => [$filter],
            'order_by' => [['column' => 'nama', 'dir' => 'asc']],
        ]);

        $this->assertSame($expectedNames, array_column($result['rows'], 'nama'));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function filters(): array
    {
        return [
            'sama dengan' => [['column' => 'status', 'op' => '=', 'value' => 'SUBMITTED'], ['Ayu', 'Budi', 'Dewi']],
            'tidak sama' => [['column' => 'status', 'op' => '!=', 'value' => 'SUBMITTED'], ['Citra', 'Eka']],
            'lebih besar' => [['column' => 'art', 'op' => '>', 'value' => 4], ['Citra', 'Eka']],
            'in' => [['column' => 'kecamatan', 'op' => 'in', 'value' => ['Susut']], ['Dewi', 'Eka']],
            'not in' => [['column' => 'kecamatan', 'op' => 'not_in', 'value' => ['Susut']], ['Ayu', 'Budi', 'Citra']],
            'between tanggal' => [['column' => 'tanggal', 'op' => 'between', 'value' => ['2026-06-02', '2026-06-03']], ['Citra', 'Dewi']],
            'like' => [['column' => 'nama', 'op' => 'like', 'value' => 'u'], ['Ayu', 'Budi']],
            'is null' => [['column' => 'tanggal', 'op' => 'is_null'], ['Eka']],
            'not null' => [['column' => 'tanggal', 'op' => 'not_null'], ['Ayu', 'Budi', 'Citra', 'Dewi']],
        ];
    }

    public function test_like_treats_percent_and_underscore_literally(): void
    {
        $result = $this->runQuery(['select' => ['nama'], 'filters' => [['column' => 'nama', 'op' => 'like', 'value' => '%']]]);

        $this->assertSame([], $result['rows']);
    }

    public function test_result_is_truncated_at_limit_and_reported(): void
    {
        $result = $this->runQuery(['select' => ['nama'], 'order_by' => [['column' => 'nama', 'dir' => 'asc']], 'limit' => 2]);

        $this->assertSame(['Ayu', 'Budi'], array_column($result['rows'], 'nama'));
        $this->assertSame(['row_count' => 2, 'limit' => 2, 'truncated' => true], array_intersect_key($result['meta'], array_flip(['row_count', 'limit', 'truncated'])));
    }

    public function test_empty_config_selects_all_columns_with_default_limit(): void
    {
        $result = $this->runQuery([]);

        $this->assertCount(5, $result['rows']);
        $this->assertSame(['kecamatan', 'nama', 'status', 'art', 'luas', 'tanggal'], array_column($result['columns'], 'name'));
        $this->assertSame(1000, $result['meta']['limit']);
        $this->assertFalse($result['meta']['truncated']);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('invalidConfigs')]
    public function test_invalid_config_is_rejected_with_clear_message(array $config, string $field, string $message): void
    {
        try {
            $this->runQuery($config);
            $this->fail('Konfigurasi seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()[$field] ?? null, json_encode($e->errors()));
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidConfigs(): array
    {
        return [
            'kolom tidak dikenal' => [['select' => ['password']], 'select.0', "Kolom 'password' tidak ada di dataset ini."],
            'sum pada teks' => [['aggregates' => [['fn' => 'sum', 'column' => 'nama', 'as' => 'total']]], 'aggregates.0.column', "Fungsi sum hanya untuk kolom angka, 'nama' bukan angka."],
            'avg tanpa kolom' => [['aggregates' => [['fn' => 'avg', 'column' => null, 'as' => 'rata']]], 'aggregates.0.column', 'Fungsi avg membutuhkan kolom.'],
            'fungsi tidak dikenal' => [['aggregates' => [['fn' => 'median', 'column' => 'art', 'as' => 'm']]], 'aggregates.0.fn', "Fungsi agregat 'median' tidak didukung."],
            'alias tidak valid' => [['aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'Jumlah Total']]], 'aggregates.0.as', 'Alias hanya boleh huruf kecil, angka, dan garis bawah, diawali huruf.'],
            'alias sama dengan kolom' => [['aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'nama']]], 'aggregates.0.as', "Alias 'nama' sama dengan nama kolom."],
            'select tanpa group_by' => [['select' => ['kecamatan'], 'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']]], 'select.0', "Kolom 'kecamatan' harus ada di group_by karena query memakai agregat."],
            'order_by bukan kolom hasil' => [['select' => ['nama'], 'order_by' => [['column' => 'art', 'dir' => 'asc']]], 'order_by.0.column', "Urutan 'art' harus berupa kolom hasil query."],
            'operator tidak dikenal' => [['filters' => [['column' => 'nama', 'op' => 'regexp', 'value' => 'a']]], 'filters.0.op', "Operator 'regexp' tidak didukung."],
            'in kosong' => [['filters' => [['column' => 'nama', 'op' => 'in', 'value' => []]]], 'filters.0.value', 'Operator in/not_in membutuhkan daftar 1–100 nilai.'],
            'between satu nilai' => [['filters' => [['column' => 'art', 'op' => 'between', 'value' => [1]]]], 'filters.0.value', 'Operator between membutuhkan tepat 2 nilai.'],
            'limit terlalu besar' => [['limit' => 10001], 'limit', 'Limit maksimal 10000 baris.'],
        ];
    }

    public function test_sql_injection_in_column_name_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->runQuery(['select' => ['nama" FROM users; --']]);
    }

    public function test_sql_injection_in_alias_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->runQuery(['aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'x" FROM users --']]]);
    }

    public function test_sql_injection_in_filter_value_is_treated_as_plain_text(): void
    {
        User::factory()->create();

        $result = $this->runQuery([
            'select' => ['nama'],
            'filters' => [['column' => 'nama', 'op' => '=', 'value' => "x' OR '1'='1"]],
        ]);

        $this->assertSame([], $result['rows']);
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_dataset_not_yet_imported_is_rejected(): void
    {
        $dataset = Dataset::factory()->create(['status' => 'processing']);

        $this->expectExceptionMessage('Dataset belum selesai diimpor.');

        app(QueryEngine::class)->run($dataset, []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function runQuery(array $config): array
    {
        return app(QueryEngine::class)->run($this->dataset(), $config);
    }

    private function dataset(): Dataset
    {
        $dataset = Dataset::factory()->create(['table_name' => 'ds_query']);

        Schema::create('ds_query', function (Blueprint $table) {
            $table->id('_row_id');
            $table->text('kecamatan')->nullable();
            $table->text('nama')->nullable();
            $table->text('status')->nullable();
            $table->integer('art')->nullable();
            $table->double('luas')->nullable();
            $table->text('tanggal')->nullable();
        });

        DB::table('ds_query')->insert([
            ['kecamatan' => 'Bangli', 'nama' => 'Ayu', 'status' => 'SUBMITTED', 'art' => 3, 'luas' => 1.5, 'tanggal' => '2026-06-01'],
            ['kecamatan' => 'Bangli', 'nama' => 'Budi', 'status' => 'SUBMITTED', 'art' => 4, 'luas' => 2.5, 'tanggal' => '2026-06-01'],
            ['kecamatan' => 'Bangli', 'nama' => 'Citra', 'status' => 'OPEN', 'art' => 5, 'luas' => 2.0, 'tanggal' => '2026-06-02'],
            ['kecamatan' => 'Susut', 'nama' => 'Dewi', 'status' => 'SUBMITTED', 'art' => 2, 'luas' => 1.0, 'tanggal' => '2026-06-03'],
            ['kecamatan' => 'Susut', 'nama' => 'Eka', 'status' => 'REJECTED', 'art' => 6, 'luas' => 3.0, 'tanggal' => null],
        ]);

        $columns = [['kecamatan', 'text'], ['nama', 'text'], ['status', 'text'], ['art', 'integer'], ['luas', 'decimal'], ['tanggal', 'date']];
        foreach ($columns as $index => [$name, $type]) {
            DatasetColumn::factory()->for($dataset)->create([
                'position' => $index + 1,
                'name' => $name,
                'label' => ucfirst($name),
                'type' => $type,
            ]);
        }

        return $dataset;
    }
}

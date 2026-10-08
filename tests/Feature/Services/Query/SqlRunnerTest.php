<?php

namespace Tests\Feature\Services\Query;

use App\Services\Query\SqlRunner;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesSqlFixtureDatabase;
use Tests\TestCase;

class SqlRunnerTest extends TestCase
{
    use UsesSqlFixtureDatabase;

    private SqlRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new SqlRunner($this->sqlFixture());
    }

    protected function tearDown(): void
    {
        $this->tearDownSqlFixture();

        parent::tearDown();
    }

    public function test_select_returns_rows_and_detected_column_types(): void
    {
        $result = $this->runner->run('SELECT kecamatan, COUNT(*) AS jumlah, AVG(jumlah_art) AS rata FROM ds_1 GROUP BY kecamatan ORDER BY kecamatan');

        $this->assertSame([['kecamatan' => 'Bangli', 'jumlah' => 2, 'rata' => 3.5], ['kecamatan' => 'Susut', 'jumlah' => 1, 'rata' => 5.0]], $result['rows']);
        $this->assertSame(
            [['name' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'text'], ['name' => 'jumlah', 'label' => 'Jumlah', 'type' => 'integer'], ['name' => 'rata', 'label' => 'Rata', 'type' => 'decimal']],
            $result['columns'],
        );
    }

    public function test_with_clause_and_trailing_semicolon_are_allowed(): void
    {
        $result = $this->runner->run("WITH s AS (SELECT * FROM ds_1 WHERE status = 'SUBMITTED') SELECT COUNT(*) AS n FROM s;");

        $this->assertSame([['n' => 2]], $result['rows']);
    }

    public function test_semicolon_inside_text_or_comment_is_not_a_second_statement(): void
    {
        $result = $this->runner->run("SELECT COUNT(*) AS n FROM ds_1 WHERE status != 'a;b' -- catatan; bukan perintah");

        $this->assertSame([['n' => 3]], $result['rows']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function forbidden(): array
    {
        $notAllowed = 'Perintah ini tidak diizinkan. Mode SQL hanya untuk SELECT/WITH tanpa rekursi.';

        return [
            'baca tabel users' => ['SELECT password FROM users', "Tabel 'users' tidak boleh dibaca. Mode SQL hanya bisa membaca tabel dataset (ds_…)."],
            'baca sqlite_master' => ['SELECT sql FROM sqlite_master', "Tabel 'sqlite_master' tidak boleh dibaca. Mode SQL hanya bisa membaca tabel dataset (ds_…)."],
            'join ke users' => ['SELECT d.kecamatan, u.password FROM ds_1 d, users u', "Tabel 'users' tidak boleh dibaca. Mode SQL hanya bisa membaca tabel dataset (ds_…)."],
            'delete' => ['DELETE FROM ds_1', $notAllowed],
            'update' => ["UPDATE ds_1 SET status = 'x'", $notAllowed],
            'insert' => ["INSERT INTO ds_1 (status) VALUES ('x')", $notAllowed],
            'drop' => ['DROP TABLE ds_1', $notAllowed],
            'pragma' => ['PRAGMA table_info(ds_1)', $notAllowed],
            'attach' => ["ATTACH DATABASE '/tmp/x.sqlite' AS x", $notAllowed],
            'rekursi tanpa batas' => ['WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r) SELECT n FROM r', $notAllowed],
            'dua perintah' => ['SELECT 1; DELETE FROM ds_1', 'Hanya satu perintah SQL yang boleh dijalankan.'],
            'kosong' => ['  ;  ', 'Tulis query SQL terlebih dahulu.'],
            'kolom ganda' => ['SELECT COUNT(*), COUNT(*) FROM ds_1', 'Nama kolom hasil harus unik. Beri alias dengan AS, misalnya COUNT(*) AS jumlah.'],
            'tabel tidak ada' => ['SELECT * FROM ds_99', "Tabel 'ds_99' tidak ditemukan. Lihat daftar tabel dataset di panel kiri."],
        ];
    }

    #[DataProvider('forbidden')]
    public function test_unsafe_or_invalid_sql_is_rejected_with_clear_message(string $sql, string $message): void
    {
        try {
            $this->runner->run($sql);
            $this->fail('SQL seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()['sql']);
        }

        $this->assertSame(3, (int) (new \PDO('sqlite:'.$this->sqlFixturePath))->query('SELECT COUNT(*) FROM ds_1')->fetchColumn());
    }

    public function test_result_is_limited_and_truncation_reported(): void
    {
        $result = $this->runner->run('SELECT kecamatan FROM ds_1 ORDER BY _row_id', 2);

        $this->assertCount(2, $result['rows']);
        $this->assertTrue($result['meta']['truncated']);
    }

    public function test_filters_are_wrapped_around_the_query_and_bound(): void
    {
        $sql = 'SELECT kecamatan, status, jumlah_art FROM ds_1';

        $this->assertSame(
            [['kecamatan' => 'Bangli', 'status' => 'SUBMITTED', 'jumlah_art' => 4]],
            $this->runner->run($sql, 100, [
                ['column' => 'kecamatan', 'op' => '=', 'value' => 'Bangli'],
                ['column' => 'status', 'op' => '=', 'value' => 'SUBMITTED'],
            ])['rows'],
        );

        $this->assertSame([], $this->runner->run($sql, 100, [['column' => 'kecamatan', 'op' => '=', 'value' => "x' OR '1'='1"]])['rows']);
    }
}

<?php

namespace App\Services\Query;

use App\Models\Dataset;
use App\Services\Import\TypeDetector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SQLite3;
use SQLite3Stmt;
use Throwable;

/**
 * Menjalankan SQL yang ditulis pengguna (mode SQL) dengan tiga lapis pengaman:
 * 1. koneksi SQLite dibuka read-only,
 * 2. authorizer SQLite hanya mengizinkan SELECT yang membaca tabel `ds_{id}`
 *    (tabel users, sessions, dan lainnya ditolak oleh SQLite sendiri),
 * 3. hanya satu perintah, dan hasil dibatasi `limit` baris.
 */
class SqlRunner
{
    public const DEFAULT_LIMIT = 1000;

    public const MAX_LIMIT = 10000;

    private const SLOW_QUERY_MS = 2000;

    private const CACHE_MINUTES = 5;

    public function __construct(private ?string $path = null) {}

    /**
     * @param  list<array{column: string, op: string, value: mixed}>  $filters  filter global dashboard
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array{row_count: int, limit: int, truncated: bool, duration_ms: int, cached: bool}}
     *
     * @throws ValidationException
     */
    public function run(string $sql, int $limit = self::DEFAULT_LIMIT, array $filters = []): array
    {
        $sql = $this->clean($sql);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $db = $this->connect();

        try {
            [$statement, $bindings] = $this->prepare($db, $sql, $filters);

            foreach ($bindings as $name => $value) {
                $statement->bindValue($name, $value);
            }

            $started = microtime(true);
            $result = $statement->execute();

            $names = [];
            for ($i = 0; $i < $result->numColumns(); $i++) {
                $names[] = $result->columnName($i);
            }

            if (count($names) !== count(array_unique($names))) {
                throw $this->error('Nama kolom hasil harus unik. Beri alias dengan AS, misalnya COUNT(*) AS jumlah.');
            }

            $rows = [];
            while (count($rows) <= $limit && ($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $rows[] = $row;
            }
            $result->finalize();
            $durationMs = (int) round((microtime(true) - $started) * 1000);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->error($this->explain($e->getMessage()));
        } finally {
            $db->close();
        }

        if ($durationMs > self::SLOW_QUERY_MS) {
            Log::warning('Query SQL lambat', ['duration_ms' => $durationMs, 'sql' => $sql]);
        }

        $truncated = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        return [
            'columns' => $this->describe($names, $rows),
            'rows' => $rows,
            'meta' => [
                'row_count' => count($rows),
                'limit' => $limit,
                'truncated' => $truncated,
                'duration_ms' => $durationMs,
                'cached' => false,
            ],
        ];
    }

    /**
     * Sama dengan run(), tetapi disimpan di cache. Kunci memuat waktu perubahan
     * dataset terakhir, sehingga import ulang membuat hasil lama tidak terpakai.
     *
     * @param  list<array{column: string, op: string, value: mixed}>  $filters
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public function cachedRun(string $sql, int $limit = self::DEFAULT_LIMIT, array $filters = []): array
    {
        $key = sprintf(
            'sql:%s:%s',
            Dataset::max('updated_at') ?? '0',
            md5(json_encode([$this->clean($sql), $limit, $filters])),
        );

        $cached = Cache::get($key);

        if (is_array($cached)) {
            $cached['meta']['cached'] = true;

            return $cached;
        }

        $result = $this->run($sql, $limit, $filters);
        Cache::put($key, $result, now()->addMinutes(self::CACHE_MINUTES));

        return $result;
    }

    /**
     * Nama kolom hasil tanpa mengambil baris data.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function columnNames(string $sql): array
    {
        return array_column($this->run($sql, 1)['columns'], 'name');
    }

    private function connect(): SQLite3
    {
        $path = $this->path ?? config('database.connections.sqlite.database');

        $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(5000);
        $db->setAuthorizer(fn (int $action, ?string $table = null) => match ($action) {
            SQLite3::SELECT, SQLite3::FUNCTION => SQLite3::OK,
            SQLite3::READ => preg_match('/^ds_\d+$/', (string) $table) === 1 ? SQLite3::OK : SQLite3::DENY,
            default => SQLite3::DENY,
        });

        return $db;
    }

    /**
     * Filter global dashboard dibungkus di luar SQL pengguna, nilai di-binding.
     *
     * @param  list<array{column: string, op: string, value: mixed}>  $filters
     * @return array{0: SQLite3Stmt, 1: array<string, mixed>}
     */
    private function prepare(SQLite3 $db, string $sql, array $filters): array
    {
        if ($filters === []) {
            return [$db->prepare($sql), []];
        }

        $conditions = [];
        $bindings = [];

        foreach ($filters as $index => $filter) {
            $column = '"'.str_replace('"', '""', $filter['column']).'"';
            $param = ":f{$index}";

            if ($filter['op'] === 'between') {
                $conditions[] = "{$column} BETWEEN {$param}a AND {$param}b";
                $bindings["{$param}a"] = $filter['value'][0];
                $bindings["{$param}b"] = $filter['value'][1];

                continue;
            }

            $operator = match ($filter['op']) {
                '>=' => '>=',
                '<=' => '<=',
                default => '=',
            };
            $conditions[] = "{$column} {$operator} {$param}";
            $bindings[$param] = $filter['value'];
        }

        $wrapped = "SELECT * FROM (\n{$sql}\n) AS hasil WHERE ".implode(' AND ', $conditions);

        return [$db->prepare($wrapped), $bindings];
    }

    /**
     * Buang komentar dan teks berkutip untuk memeriksa titik koma: hanya satu
     * perintah yang boleh dijalankan.
     */
    private function clean(string $sql): string
    {
        $sql = rtrim(trim($sql), "; \n\r\t");

        if ($sql === '') {
            throw $this->error('Tulis query SQL terlebih dahulu.');
        }

        $stripped = preg_replace(["/'(?:[^']|'')*'/", '/"(?:[^"]|"")*"/', '/--[^\n]*/', '/\/\*.*?\*\//s'], '', $sql);

        if (str_contains((string) $stripped, ';')) {
            throw $this->error('Hanya satu perintah SQL yang boleh dijalankan.');
        }

        return $sql;
    }

    /**
     * @param  list<string>  $names
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{name: string, label: string, type: string}>
     */
    private function describe(array $names, array $rows): array
    {
        $detector = new TypeDetector;
        foreach ($rows as $row) {
            $detector->observe(array_values($row));
        }
        $types = $detector->types(count($names));

        return array_map(fn (string $name, string $type) => [
            'name' => $name,
            'label' => Str::of($name)->replace('_', ' ')->ucfirst()->toString(),
            'type' => $type,
        ], $names, $types);
    }

    private function explain(string $message): string
    {
        return match (true) {
            preg_match('/access to (\w+)\.\w+ is prohibited/', $message, $m) === 1 => "Tabel '{$m[1]}' tidak boleh dibaca. Mode SQL hanya bisa membaca tabel dataset (ds_…).",
            str_contains($message, 'not authorized') => 'Perintah ini tidak diizinkan. Mode SQL hanya untuk SELECT/WITH tanpa rekursi.',
            preg_match('/no such table: (\S+)/', $message, $m) === 1 => "Tabel '{$m[1]}' tidak ditemukan. Lihat daftar tabel dataset di panel kiri.",
            preg_match('/no such column: (\S+)/', $message, $m) === 1 => "Kolom '{$m[1]}' tidak ditemukan.",
            default => 'Kesalahan SQL: '.preg_replace('/^Unable to prepare statement: (\d+, )?/', '', $message),
        };
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['sql' => $message]);
    }
}

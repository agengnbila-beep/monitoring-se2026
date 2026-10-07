<?php

namespace App\Console\Commands;

use App\Jobs\ImportDataset;
use App\Models\Dashboard;
use App\Models\Dataset;
use App\Services\Query\QueryEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;
use Throwable;

#[Signature('se2026:import-legacy {path : Path ke file database.sqlite web monitoring lama} {--fresh : Hapus dataset dan dashboard hasil migrasi sebelumnya}')]
#[Description('Migrasi data web monitoring SE2026 lama menjadi dataset, saved query, dan dashboard')]
class ImportLegacySe2026 extends Command
{
    public const MONITORING = 'SE2026 Lama - Monitoring Assignment';

    public const FASIH = 'SE2026 Lama - Hasil FASIH';

    public const DASHBOARD = 'Monitoring SE2026 (Data Lama)';

    public function handle(QueryEngine $engine): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $legacy = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        ]);

        if (! $this->prepareTarget()) {
            return self::FAILURE;
        }

        $this->info('1/4 Ekspor dan impor monitoring_records…');
        $monitoring = $this->importTable($legacy, self::MONITORING, 'legacy-monitoring-records.csv', <<<'SQL'
            SELECT full_code,
                   substr(full_code, 5, 3) AS kode_kecamatan,
                   substr(full_code, 8, 3) AS kode_desa,
                   kecamatan, desa, sls,
                   petugas_username, petugas_name, nama_krt,
                   status, date_modified,
                   substr(date_modified, 1, 10) AS tanggal_modifikasi,
                   assignment_id
            FROM monitoring_records
            ORDER BY id
            SQL);

        $this->info('2/4 Ekspor dan impor fasih_responses…');
        $fasih = $this->importTable($legacy, self::FASIH, 'legacy-fasih-responses.csv', <<<'SQL'
            SELECT f.assignment_id,
                   m.kecamatan, m.desa, m.petugas_username,
                   f.synced_status AS status,
                   f.total_biz_count, f.digital_biz_count, f.not_found_biz_count,
                   substr(f.created_at, 1, 10) AS tanggal_sinkron
            FROM fasih_responses f
            LEFT JOIN monitoring_records m ON m.assignment_id = f.assignment_id
            ORDER BY f.id
            SQL);

        $this->info('3/4 Membuat saved query dan dashboard…');
        $this->createDashboard($engine, $monitoring, $fasih);

        $this->info('4/4 Verifikasi angka terhadap database lama…');

        return $this->verify($legacy, $engine, $monitoring, $fasih) ? self::SUCCESS : self::FAILURE;
    }

    private function prepareTarget(): bool
    {
        $existing = Dataset::whereIn('name', [self::MONITORING, self::FASIH])->get();
        $dashboard = Dashboard::where('name', self::DASHBOARD)->first();

        if ($existing->isEmpty() && $dashboard === null) {
            return true;
        }

        if (! $this->option('fresh')) {
            $this->error('Data hasil migrasi sebelumnya sudah ada. Jalankan ulang dengan --fresh untuk menggantinya.');

            return false;
        }

        // Widget ikut terhapus bersama dashboard, sehingga saved query hasil
        // migrasi tidak lagi dipakai dan bisa dihapus sebelum dataset-nya.
        $dashboard?->delete();

        foreach ($existing as $dataset) {
            $dataset->savedQueries()->delete();

            if ($dataset->table_name !== null) {
                Schema::dropIfExists($dataset->table_name);
            }

            if ($dataset->file_path !== null) {
                Storage::disk('local')->delete($dataset->file_path);
            }

            $dataset->delete();
        }

        return true;
    }

    /**
     * Ekspor hasil SQL ke CSV di storage privat secara streaming, lalu impor
     * lewat proses yang sama dengan upload biasa (deteksi tipe + profil).
     */
    private function importTable(PDO $legacy, string $name, string $filename, string $sql): Dataset
    {
        $path = 'uploads/'.$filename;
        Storage::disk('local')->makeDirectory('uploads');
        $handle = fopen(Storage::disk('local')->path($path), 'w');

        $rows = 0;
        foreach ($legacy->query($sql, PDO::FETCH_ASSOC) as $row) {
            if ($rows === 0) {
                fputcsv($handle, array_keys($row), ',', '"', '');
            }
            fputcsv($handle, $row, ',', '"', '');
            $rows++;
        }
        fclose($handle);

        $dataset = Dataset::create([
            'name' => $name,
            'description' => 'Hasil migrasi dari web monitoring SE2026 lama.',
            'original_filename' => $filename,
            'file_path' => $path,
            'file_type' => 'csv',
            'file_size' => Storage::disk('local')->size($path),
            'status' => 'pending',
        ]);

        try {
            ImportDataset::dispatchSync($dataset);
        } catch (Throwable $e) {
            $this->error("Import {$name} gagal: {$e->getMessage()}");

            throw $e;
        }

        $dataset->refresh();
        $this->line("   {$name}: ".number_format($dataset->row_count).' baris, '.$dataset->column_count.' kolom');

        return $dataset;
    }

    private function createDashboard(QueryEngine $engine, Dataset $monitoring, Dataset $fasih): void
    {
        $count = [['fn' => 'count', 'column' => null, 'as' => 'jumlah']];

        $query = fn (Dataset $dataset, string $name, array $config) => $dataset->savedQueries()->create([
            'name' => $name,
            'config' => $engine->normalize($dataset, $config),
        ]);

        $total = $query($monitoring, 'Total assignment', ['aggregates' => $count]);
        $selesai = $query($monitoring, 'Assignment disetujui pengawas', [
            'aggregates' => $count,
            'filters' => [['column' => 'status', 'op' => '=', 'value' => 'APPROVED BY Pengawas']],
        ]);
        $perStatus = $query($monitoring, 'Assignment per status', [
            'select' => ['status'], 'aggregates' => $count, 'group_by' => ['status'],
            'order_by' => [['column' => 'jumlah', 'dir' => 'desc']],
        ]);
        $perKecamatan = $query($monitoring, 'Assignment per kecamatan', [
            'select' => ['kecamatan'], 'aggregates' => $count, 'group_by' => ['kecamatan'],
            'filters' => [['column' => 'kecamatan', 'op' => 'not_null']],
            'order_by' => [['column' => 'jumlah', 'dir' => 'desc']],
        ]);
        $perTanggal = $query($monitoring, 'Assignment diperbarui per tanggal', [
            'select' => ['tanggal_modifikasi'], 'aggregates' => $count, 'group_by' => ['tanggal_modifikasi'],
            'filters' => [['column' => 'tanggal_modifikasi', 'op' => 'not_null']],
            'order_by' => [['column' => 'tanggal_modifikasi', 'dir' => 'asc']],
        ]);
        $perPetugas = $query($monitoring, 'Assignment per petugas', [
            'select' => ['petugas_username'],
            'aggregates' => [...$count, ['fn' => 'max', 'column' => 'date_modified', 'as' => 'terakhir_diperbarui']],
            'group_by' => ['petugas_username'],
            'filters' => [['column' => 'petugas_username', 'op' => 'not_null']],
            'order_by' => [['column' => 'jumlah', 'dir' => 'desc']],
        ]);
        $usaha = $query($fasih, 'Total usaha ditemukan', [
            'aggregates' => [['fn' => 'sum', 'column' => 'total_biz_count', 'as' => 'total_usaha']],
        ]);
        $usahaDigital = $query($fasih, 'Total usaha digital', [
            'aggregates' => [['fn' => 'sum', 'column' => 'digital_biz_count', 'as' => 'usaha_digital']],
        ]);
        $usahaPerKecamatan = $query($fasih, 'Usaha per kecamatan', [
            'select' => ['kecamatan'],
            'aggregates' => [
                ['fn' => 'sum', 'column' => 'total_biz_count', 'as' => 'total_usaha'],
                ['fn' => 'sum', 'column' => 'digital_biz_count', 'as' => 'usaha_digital'],
            ],
            'group_by' => ['kecamatan'],
            'filters' => [['column' => 'kecamatan', 'op' => 'not_null']],
            'order_by' => [['column' => 'total_usaha', 'dir' => 'desc']],
        ]);

        $dashboard = Dashboard::create([
            'name' => self::DASHBOARD,
            'slug' => 'monitoring-se2026-data-lama',
            'description' => 'Migrasi dari web monitoring SE2026 lama.',
            'filters' => [
                ['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'select'],
                ['key' => 'tanggal_modifikasi', 'label' => 'Tanggal diperbarui', 'type' => 'date_range'],
            ],
        ]);

        $widgets = [
            [$total, 'Total assignment', 'kpi', ['value' => 'jumlah', 'format' => 'number'], 3],
            [$selesai, 'Disetujui pengawas', 'kpi', ['value' => 'jumlah', 'format' => 'number'], 3],
            [$usaha, 'Usaha ditemukan', 'kpi', ['value' => 'total_usaha', 'format' => 'number'], 3],
            [$usahaDigital, 'Usaha digital', 'kpi', ['value' => 'usaha_digital', 'format' => 'number'], 3],
            [$perStatus, 'Assignment per status', 'bar', ['x' => 'status', 'y' => ['jumlah'], 'stacked' => false], 6],
            [$perKecamatan, 'Assignment per kecamatan', 'bar', ['x' => 'kecamatan', 'y' => ['jumlah'], 'stacked' => false], 6],
            [$perTanggal, 'Assignment diperbarui per tanggal', 'line', ['x' => 'tanggal_modifikasi', 'y' => ['jumlah'], 'stacked' => false], 12],
            [$usahaPerKecamatan, 'Usaha per kecamatan', 'bar', ['x' => 'kecamatan', 'y' => ['total_usaha', 'usaha_digital'], 'stacked' => false], 12],
            [$perPetugas, 'Progres per petugas', 'table', ['columns' => ['petugas_username', 'jumlah', 'terakhir_diperbarui']], 12],
        ];

        foreach ($widgets as $order => [$savedQuery, $title, $type, $options, $width]) {
            $dashboard->widgets()->create([
                'saved_query_id' => $savedQuery->id,
                'title' => $title,
                'chart_type' => $type,
                'options' => $options,
                'sort_order' => $order + 1,
                'width' => $width,
            ]);
        }

        $this->line("   Dashboard \"{$dashboard->name}\": ".count($widgets).' widget');
    }

    /**
     * Bandingkan angka dashboard baru dengan hasil SQL langsung di database lama.
     */
    private function verify(PDO $legacy, QueryEngine $engine, Dataset $monitoring, Dataset $fasih): bool
    {
        $old = fn (string $sql) => $legacy->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
        $new = fn (Dataset $dataset, array $config, string $key, string $value) => collect($engine->run($dataset, $config)['rows'])
            ->mapWithKeys(fn (array $row) => [(string) ($row[$key] ?? '') => $row[$value]])
            ->all();

        $count = [['fn' => 'count', 'column' => null, 'as' => 'n']];
        $checks = [
            'Total assignment' => [
                $old("SELECT 'total', COUNT(*) FROM monitoring_records"),
                ['total' => $engine->run($monitoring, ['aggregates' => $count])['rows'][0]['n']],
            ],
            'Assignment per status' => [
                $old('SELECT COALESCE(status, \'\'), COUNT(*) FROM monitoring_records GROUP BY status'),
                $new($monitoring, ['select' => ['status'], 'aggregates' => $count, 'group_by' => ['status']], 'status', 'n'),
            ],
            'Assignment per kecamatan' => [
                $old("SELECT COALESCE(NULLIF(kecamatan, ''), ''), COUNT(*) FROM monitoring_records GROUP BY 1"),
                $new($monitoring, ['select' => ['kecamatan'], 'aggregates' => $count, 'group_by' => ['kecamatan']], 'kecamatan', 'n'),
            ],
            'Usaha per status FASIH' => [
                $old("SELECT COALESCE(synced_status, ''), SUM(total_biz_count) FROM fasih_responses GROUP BY 1"),
                $new($fasih, ['select' => ['status'], 'aggregates' => [['fn' => 'sum', 'column' => 'total_biz_count', 'as' => 'n']], 'group_by' => ['status']], 'status', 'n'),
            ],
            'Total usaha digital' => [
                $old("SELECT 'total', SUM(digital_biz_count) FROM fasih_responses"),
                ['total' => $engine->run($fasih, ['aggregates' => [['fn' => 'sum', 'column' => 'digital_biz_count', 'as' => 'n']]])['rows'][0]['n']],
            ],
        ];

        $allMatch = true;
        $table = [];
        foreach ($checks as $label => [$expected, $actual]) {
            ksort($expected);
            ksort($actual);
            $expected = array_map('intval', $expected);
            $actual = array_map('intval', $actual);
            $match = $expected === $actual;
            $allMatch = $allMatch && $match;
            $table[] = [$label, count($expected).' nilai', $match ? 'cocok' : 'BEDA'];

            if (! $match) {
                $this->warn("   {$label}: lama=".json_encode($expected).' baru='.json_encode($actual));
            }
        }

        $this->table(['Pemeriksaan', 'Jumlah angka', 'Hasil'], $table);

        $allMatch
            ? $this->info('Semua angka cocok dengan database lama.')
            : $this->error('Ada angka yang tidak cocok. Periksa peringatan di atas.');

        return $allMatch;
    }
}

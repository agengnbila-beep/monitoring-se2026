<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ImportLegacySe2026;
use App\Models\Dashboard;
use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class ImportLegacySe2026Test extends TestCase
{
    use RefreshDatabase;

    private string $legacyPath;

    protected function tearDown(): void
    {
        @unlink($this->legacyPath ?? '');

        parent::tearDown();
    }

    public function test_legacy_data_is_migrated_and_verified(): void
    {
        Storage::fake('local');

        $this->artisan('se2026:import-legacy', ['path' => $this->legacyDatabase()])
            ->expectsOutputToContain('Semua angka cocok dengan database lama.')
            ->assertSuccessful();

        $monitoring = Dataset::where('name', ImportLegacySe2026::MONITORING)->sole();
        $this->assertSame('done', $monitoring->status);
        $this->assertSame(3, $monitoring->row_count);
        $this->assertSame('010', $monitoring->columns()->where('name', 'kode_kecamatan')->sole()->min_value);

        $fasih = Dataset::where('name', ImportLegacySe2026::FASIH)->sole();
        $this->assertSame(2, $fasih->row_count);

        $dashboard = Dashboard::where('name', ImportLegacySe2026::DASHBOARD)->sole();
        $this->assertSame(9, $dashboard->widgets()->count());
    }

    public function test_second_run_requires_fresh_option(): void
    {
        Storage::fake('local');
        $path = $this->legacyDatabase();
        $this->artisan('se2026:import-legacy', ['path' => $path])->assertSuccessful();

        $this->artisan('se2026:import-legacy', ['path' => $path])
            ->expectsOutputToContain('Jalankan ulang dengan --fresh')
            ->assertFailed();

        $this->artisan('se2026:import-legacy', ['path' => $path, '--fresh' => true])->assertSuccessful();

        $this->assertSame(1, Dashboard::where('name', ImportLegacySe2026::DASHBOARD)->count());
        $this->assertSame(1, Dataset::where('name', ImportLegacySe2026::MONITORING)->count());
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('se2026:import-legacy', ['path' => '/tidak/ada.sqlite'])
            ->expectsOutputToContain('File tidak ditemukan')
            ->assertFailed();
    }

    private function legacyDatabase(): string
    {
        $this->legacyPath = sys_get_temp_dir().'/'.uniqid('legacy_', true).'.sqlite';
        $pdo = new PDO('sqlite:'.$this->legacyPath);

        $pdo->exec('CREATE TABLE monitoring_records (id INTEGER PRIMARY KEY, full_code TEXT, kecamatan TEXT, desa TEXT, sls TEXT,
            petugas_username TEXT, petugas_name TEXT, nama_krt TEXT, status TEXT, date_modified TEXT, assignment_id TEXT)');
        $pdo->exec('CREATE TABLE fasih_responses (id INTEGER PRIMARY KEY, assignment_id TEXT, synced_status TEXT,
            total_biz_count INTEGER, digital_biz_count INTEGER, not_found_biz_count INTEGER, created_at TEXT)');

        $pdo->exec("INSERT INTO monitoring_records (full_code, kecamatan, desa, sls, petugas_username, petugas_name, nama_krt, status, date_modified, assignment_id) VALUES
            ('1504010013000200', '[010] MERSAM', '[013] KEMBANG', 'RT 002', 'a@x.id', 'A', 'KRT 1', 'APPROVED BY Pengawas', '2026-06-15 11:28:16', 'as-1'),
            ('1504010013000200', '[010] MERSAM', '[013] KEMBANG', 'RT 002', 'a@x.id', 'A', 'KRT 2', 'OPEN', '2026-06-16 08:00:00', 'as-2'),
            ('1504020001000100', NULL, NULL, NULL, 'b@x.id', NULL, 'KRT 3', 'OPEN', '2026-06-16 09:00:00', 'as-3')");
        $pdo->exec("INSERT INTO fasih_responses (assignment_id, synced_status, total_biz_count, digital_biz_count, not_found_biz_count, created_at) VALUES
            ('as-1', 'APPROVED BY Pengawas', 3, 1, 0, '2026-06-17 00:50:13'),
            ('as-3', NULL, 2, 0, 1, '2026-06-17 00:51:00')");

        return $this->legacyPath;
    }
}

<?php

namespace Database\Seeders;

use App\Jobs\ImportDataset;
use App\Models\Dataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatasetSeeder extends Seeder
{
    /**
     * Dataset contoh yang benar-benar diimpor, agar laman Overview
     * dan endpoint profil punya data untuk ditampilkan.
     */
    public function run(): void
    {
        $this->importCsv('Data Penduduk SE2026', 'data-penduduk-se2026.csv', <<<'CSV'
            kecamatan;desa;nama_krt;jumlah_art;status;tanggal_pendataan
            Bangli;Kubu;I Made Sudarma;4;SUBMITTED;2026-06-01
            Bangli;Kubu;Ni Ketut Ayu;3;SUBMITTED;2026-06-01
            Bangli;Cempaga;I Wayan Gede;5;OPEN;2026-06-02
            Susut;Abuan;Ni Luh Sari;2;SUBMITTED;2026-06-03
            Susut;Abuan;I Nyoman Putra;6;REJECTED;2026-06-03
            Tembuku;Jehem;I Ketut Darma;;OPEN;
            CSV);

        $this->importCsv('Data Wilayah', 'data-wilayah.csv', <<<'CSV'
            kode_kecamatan,kecamatan,jumlah_desa,luas_km2
            010,Susut,9,49.31
            020,Bangli,9,52.81
            030,Tembuku,6,48.32
            040,Kintamani,48,366.92
            CSV);
    }

    private function importCsv(string $name, string $filename, string $contents): void
    {
        $path = 'uploads/seed-'.$filename;
        Storage::disk('local')->put($path, $contents."\n");

        $dataset = Dataset::create([
            'name' => $name,
            'original_filename' => $filename,
            'file_path' => $path,
            'file_type' => 'csv',
            'file_size' => Storage::disk('local')->size($path),
            'status' => 'pending',
        ]);

        ImportDataset::dispatchSync($dataset);
    }
}

<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportDataset;
use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportDatasetTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_is_imported_into_its_own_table_with_detected_types(): void
    {
        Storage::fake('local');
        $dataset = $this->datasetWithFile('csv', "Nama KRT;Umur;Nilai;Tanggal\nBudi;20;3.5;2026-06-01\nSiti;;10;\n");

        ImportDataset::dispatchSync($dataset);

        $dataset->refresh();
        $this->assertSame('done', $dataset->status);
        $this->assertSame('ds_'.$dataset->id, $dataset->table_name);
        $this->assertSame(2, $dataset->row_count);
        $this->assertSame(4, $dataset->column_count);

        $this->assertSame(
            [
                ['nama_krt', 'Nama KRT', 'text'],
                ['umur', 'Umur', 'integer'],
                ['nilai', 'Nilai', 'decimal'],
                ['tanggal', 'Tanggal', 'date'],
            ],
            $dataset->columns()->get()->map(fn ($column) => [$column->name, $column->label, $column->type])->all(),
        );

        $this->assertSame(
            [
                ['nama_krt' => 'Budi', 'umur' => 20, 'nilai' => 3.5, 'tanggal' => '2026-06-01'],
                ['nama_krt' => 'Siti', 'umur' => null, 'nilai' => 10.0, 'tanggal' => null],
            ],
            DB::table($dataset->table_name)->orderBy('_row_id')->get(['nama_krt', 'umur', 'nilai', 'tanggal'])
                ->map(fn ($row) => (array) $row)->all(),
        );
    }

    public function test_all_rows_are_imported_across_chunk_boundaries(): void
    {
        Storage::fake('local');
        $dataset = $this->datasetWithFile('csv', "no\n".implode("\n", range(1, 1001))."\n");

        ImportDataset::dispatchSync($dataset);

        $this->assertSame(1001, $dataset->refresh()->row_count);
        $this->assertSame(1001, DB::table($dataset->table_name)->count());
        $this->assertSame(1001, DB::table($dataset->table_name)->max('no'));
    }

    public function test_short_rows_are_padded_and_extra_cells_are_ignored(): void
    {
        Storage::fake('local');
        $dataset = $this->datasetWithFile('csv', "a,b\n1\n2,3,4\n");

        ImportDataset::dispatchSync($dataset);

        $this->assertSame(
            [['a' => 1, 'b' => null], ['a' => 2, 'b' => 3]],
            DB::table($dataset->refresh()->table_name)->orderBy('_row_id')->get(['a', 'b'])
                ->map(fn ($row) => (array) $row)->all(),
        );
    }

    public function test_xlsx_import_reads_the_selected_sheet(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('uploads');
        $path = 'uploads/rekap.xlsx';

        $writer = new Writer;
        $writer->openToFile(Storage::disk('local')->path($path));
        $writer->getCurrentSheet()->setName('Ringkasan');
        $writer->addRow(Row::fromValues(['judul']));
        $writer->addNewSheetAndMakeItCurrent()->setName('Data');
        $writer->addRow(Row::fromValues(['kecamatan', 'jumlah']));
        $writer->addRow(Row::fromValues(['Bangli', 12]));
        $writer->close();

        $dataset = Dataset::factory()->create([
            'file_path' => $path,
            'file_type' => 'xlsx',
            'sheet_name' => 'Data',
            'status' => 'pending',
        ]);

        ImportDataset::dispatchSync($dataset);

        $this->assertSame(
            [['kecamatan' => 'Bangli', 'jumlah' => 12]],
            DB::table($dataset->refresh()->table_name)->get(['kecamatan', 'jumlah'])->map(fn ($row) => (array) $row)->all(),
        );
    }

    public function test_unreadable_file_marks_dataset_failed_and_leaves_no_table(): void
    {
        Storage::fake('local');
        $dataset = $this->datasetWithFile('json', '[1, 2, 3]');

        $this->assertThrows(fn () => ImportDataset::dispatchSync($dataset));

        $dataset->refresh();
        $this->assertSame('failed', $dataset->status);
        $this->assertSame('JSON harus berupa array of objects, contoh: [{"nama": "A"}, ...].', $dataset->error_message);
        $this->assertNull($dataset->table_name);
        $this->assertFalse(Schema::hasTable('ds_'.$dataset->id));
        $this->assertSame(0, $dataset->columns()->count());
    }

    public function test_empty_file_marks_dataset_failed(): void
    {
        Storage::fake('local');
        $dataset = $this->datasetWithFile('csv', '');

        $this->assertThrows(fn () => ImportDataset::dispatchSync($dataset));

        $this->assertSame('File tidak berisi data.', $dataset->refresh()->error_message);
    }

    private function datasetWithFile(string $type, string $contents): Dataset
    {
        $path = 'uploads/data.'.$type;
        Storage::disk('local')->put($path, $contents);

        return Dataset::factory()->create([
            'file_path' => $path,
            'file_type' => $type,
            'status' => 'pending',
        ]);
    }
}

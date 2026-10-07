<?php

namespace Tests\Feature;

use App\Jobs\ImportDataset;
use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class DatasetUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_upload_csv_and_is_sent_to_preview(): void
    {
        $file = UploadedFile::fake()->createWithContent('penduduk.csv', "nama;umur\nBudi;20\n");

        $response = $this->actingAs($this->admin)->post('/data/upload', ['file' => $file]);

        $dataset = Dataset::sole();
        $response->assertRedirect(route('data.preview', $dataset));
        $this->assertSame('uploaded', $dataset->status);
        $this->assertSame('csv', $dataset->file_type);
        $this->assertSame('penduduk', $dataset->name);
        $this->assertSame($this->admin->id, $dataset->created_by);
        Storage::disk('local')->assertExists($dataset->file_path);
    }

    public function test_upload_via_javascript_returns_preview_url_as_json(): void
    {
        $file = UploadedFile::fake()->createWithContent('penduduk.csv', "nama\nBudi\n");

        $response = $this->actingAs($this->admin)->postJson('/data/upload', ['file' => $file]);

        $response->assertCreated()
            ->assertExactJson(['redirect' => route('data.preview', Dataset::sole())]);
    }

    public function test_upload_via_javascript_returns_validation_message_as_json(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/data/upload', ['file' => UploadedFile::fake()->create('laporan.pdf', 10)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Format file harus CSV, XLSX, atau JSON.']);
    }

    public function test_file_rejected_by_php_upload_limit_gets_clear_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $file = new UploadedFile($path, 'besar.csv', null, UPLOAD_ERR_INI_SIZE, true);

        $this->actingAs($this->admin)
            ->postJson('/data/upload', ['file' => $file])
            ->assertJsonValidationErrors(['file' => 'File gagal diupload. Ukurannya mungkin melebihi batas server.']);
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/data/upload', ['file' => UploadedFile::fake()->create('laporan.pdf', 10)])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('datasets', 0);
    }

    public function test_file_larger_than_20_mb_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/data/upload', ['file' => UploadedFile::fake()->create('besar.csv', 20481)])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('datasets', 0);
    }

    public function test_viewer_cannot_upload(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/data/upload', ['file' => UploadedFile::fake()->createWithContent('a.csv', "x\n1\n")])
            ->assertForbidden();
    }

    public function test_preview_shows_headers_and_rows(): void
    {
        $dataset = $this->upload('penduduk.csv', "nama;umur\nBudi;20\nSiti;31\n");

        $this->get(route('data.preview', $dataset))
            ->assertOk()
            ->assertSeeInOrder(['<th>nama</th>', '<th>umur</th>', '>Budi</td>', '>Siti</td>'], false);
    }

    public function test_preview_shows_at_most_twenty_rows(): void
    {
        $dataset = $this->upload('angka.csv', "no\n".implode("\n", range(1, 50))."\n");

        $this->get(route('data.preview', $dataset))
            ->assertOk()
            ->assertSee('>20</td>', false)
            ->assertDontSee('>21</td>', false);
    }

    public function test_xlsx_preview_lists_sheets_and_can_switch_sheet(): void
    {
        $dataset = $this->uploadXlsx();

        $this->get(route('data.preview', $dataset))
            ->assertOk()
            ->assertSee('<option value="Data"', false)
            ->assertSee('>abaikan</td>', false);

        $this->get(route('data.preview', [$dataset, 'sheet' => 'Data']))
            ->assertOk()
            ->assertSee('>Bangli</td>', false)
            ->assertDontSee('>abaikan</td>', false);
    }

    public function test_confirm_saves_name_and_sheet_and_queues_import(): void
    {
        Queue::fake([ImportDataset::class]);
        $dataset = $this->uploadXlsx();

        $this->post(route('data.confirm', $dataset), ['name' => 'Rekap Bangli', 'sheet' => 'Data'])
            ->assertRedirect(route('data'));

        $dataset->refresh();
        $this->assertSame('Rekap Bangli', $dataset->name);
        $this->assertSame('Data', $dataset->sheet_name);
        $this->assertSame('pending', $dataset->status);
        Queue::assertPushed(ImportDataset::class, fn (ImportDataset $job) => $job->dataset->is($dataset));
    }

    public function test_confirm_rejects_unknown_sheet(): void
    {
        $dataset = $this->uploadXlsx();

        $this->post(route('data.confirm', $dataset), ['name' => 'Rekap', 'sheet' => 'Palsu'])
            ->assertSessionHasErrors('sheet');
    }

    public function test_invalid_json_shows_error_instead_of_preview(): void
    {
        $dataset = $this->upload('data.json', '[1, 2, 3]');

        $this->get(route('data.preview', $dataset))
            ->assertOk()
            ->assertSee('File tidak bisa dibaca')
            ->assertDontSee('Lanjutkan Import');
    }

    public function test_deleting_imported_dataset_drops_its_data_table(): void
    {
        $dataset = Dataset::factory()->create(['table_name' => 'ds_99']);
        Schema::create('ds_99', fn ($table) => $table->id('_row_id'));

        $this->actingAs($this->admin)
            ->delete(route('data.destroy', $dataset))
            ->assertRedirect(route('data'));

        $this->assertModelMissing($dataset);
        $this->assertFalse(Schema::hasTable('ds_99'));
    }

    public function test_dataset_being_imported_cannot_be_deleted(): void
    {
        $dataset = Dataset::factory()->create(['status' => 'processing']);

        $this->actingAs($this->admin)
            ->from(route('data'))
            ->delete(route('data.destroy', $dataset))
            ->assertRedirect(route('data'))
            ->assertSessionHas('error', 'Dataset sedang diimpor, tunggu sampai selesai.');

        $this->assertModelExists($dataset);
    }

    public function test_dataset_name_cannot_break_out_of_delete_confirmation_script(): void
    {
        Dataset::factory()->create(['name' => "x'); alert(1); ('"]);

        $this->actingAs($this->admin)
            ->get(route('data'))
            ->assertOk()
            ->assertSee('confirm(\'Hapus dataset x\\u0027); alert(1); (\\u0027?\')', false);
    }

    public function test_admin_can_delete_uploaded_dataset_and_its_file(): void
    {
        $dataset = $this->upload('penduduk.csv', "nama\nBudi\n");

        $this->delete(route('data.destroy', $dataset))
            ->assertRedirect(route('data'));

        $this->assertModelMissing($dataset);
        Storage::disk('local')->assertMissing($dataset->file_path);
    }

    private function upload(string $filename, string $contents): Dataset
    {
        $this->actingAs($this->admin)->post('/data/upload', [
            'file' => UploadedFile::fake()->createWithContent($filename, $contents),
        ]);

        return Dataset::latest('id')->firstOrFail();
    }

    private function uploadXlsx(): Dataset
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Ringkasan');
        $writer->addRow(Row::fromValues(['judul']));
        $writer->addRow(Row::fromValues(['abaikan']));
        $writer->addNewSheetAndMakeItCurrent()->setName('Data');
        $writer->addRow(Row::fromValues(['kecamatan']));
        $writer->addRow(Row::fromValues(['Bangli']));
        $writer->close();

        $this->actingAs($this->admin)->post('/data/upload', [
            'file' => new UploadedFile($path, 'rekap.xlsx', null, null, true),
        ]);

        return Dataset::latest('id')->firstOrFail();
    }
}

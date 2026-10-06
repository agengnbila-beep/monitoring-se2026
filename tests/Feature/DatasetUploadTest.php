<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->assertSame('pending', $dataset->status);
        $this->assertSame('csv', $dataset->file_type);
        $this->assertSame('penduduk', $dataset->name);
        $this->assertSame($this->admin->id, $dataset->created_by);
        Storage::disk('local')->assertExists($dataset->file_path);
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
            ->assertSeeInOrder(['<th>nama</th>', '<th>umur</th>', '<td>Budi</td>', '<td>Siti</td>'], false);
    }

    public function test_preview_shows_at_most_twenty_rows(): void
    {
        $dataset = $this->upload('angka.csv', "no\n".implode("\n", range(1, 50))."\n");

        $this->get(route('data.preview', $dataset))
            ->assertOk()
            ->assertSee('<td>20</td>', false)
            ->assertDontSee('<td>21</td>', false);
    }

    public function test_xlsx_preview_lists_sheets_and_can_switch_sheet(): void
    {
        $dataset = $this->uploadXlsx();

        $this->get(route('data.preview', $dataset))
            ->assertOk()
            ->assertSee('<option value="Data"', false)
            ->assertSee('<td>abaikan</td>', false);

        $this->get(route('data.preview', [$dataset, 'sheet' => 'Data']))
            ->assertOk()
            ->assertSee('<td>Bangli</td>', false)
            ->assertDontSee('<td>abaikan</td>', false);
    }

    public function test_confirm_saves_name_and_sheet(): void
    {
        $dataset = $this->uploadXlsx();

        $this->post(route('data.confirm', $dataset), ['name' => 'Rekap Bangli', 'sheet' => 'Data'])
            ->assertRedirect(route('data'));

        $dataset->refresh();
        $this->assertSame('Rekap Bangli', $dataset->name);
        $this->assertSame('Data', $dataset->sheet_name);
        $this->assertSame('pending', $dataset->status);
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

    public function test_admin_can_delete_pending_dataset_and_its_file(): void
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

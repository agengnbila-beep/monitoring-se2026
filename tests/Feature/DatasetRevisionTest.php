<?php

namespace Tests\Feature;

use App\Jobs\ImportDataset;
use App\Models\Dataset;
use App\Models\DatasetRevision;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetRevisionTest extends TestCase
{
    use RefreshDatabase;

    private const COUNT_PER_KECAMATAN = [
        'select' => ['kecamatan'],
        'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']],
        'filters' => [],
        'group_by' => ['kecamatan'],
        'order_by' => [['column' => 'kecamatan', 'dir' => 'asc']],
        'limit' => 100,
    ];

    public function test_upload_creates_revision_and_sends_to_preview(): void
    {
        Storage::fake('local');
        $dataset = $this->importedDataset();

        $response = $this->actingAs(User::factory()->admin()->create())->postJson('/data/update', [
            'dataset_id' => $dataset->id,
            'file' => UploadedFile::fake()->createWithContent('baru.csv', "Kecamatan,Desa\nBangli,Kubu\n"),
        ]);

        $revision = DatasetRevision::sole();
        $response->assertCreated()->assertExactJson(['redirect' => route('data.revisions.preview', $revision)]);
        $this->assertSame('uploaded', $revision->status);
        Storage::disk('local')->assertExists($revision->file_path);
    }

    public function test_preview_lists_added_and_removed_columns_with_affected_items(): void
    {
        Storage::fake('local');
        $dataset = $this->importedDataset();
        $filter = SavedQuery::factory()->for($dataset)->create([
            'name' => 'Per desa',
            'config' => [...self::COUNT_PER_KECAMATAN, 'select' => ['desa'], 'group_by' => ['desa'], 'order_by' => []],
        ]);
        Widget::factory()->for($filter)->create(['title' => 'Grafik desa']);
        SavedQuery::factory()->sql("SELECT desa FROM ds_{$dataset->id}")->create(['name' => 'Query desa']);

        $revision = $this->uploadRevision($dataset, "Kecamatan,Status\nBangli,OPEN\n");

        $this->get(route('data.revisions.preview', $revision))
            ->assertOk()
            ->assertSee('<code class="chip chip-added">status</code>', false)
            ->assertSee('<code class="chip chip-removed">desa</code>', false)
            ->assertSee('Filter Data: Per desa')
            ->assertSee('Query SQL: Query desa')
            ->assertSee('Widget: Grafik desa');
    }

    public function test_confirming_replaces_data_and_dashboard_results_follow(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $dataset = $this->importedDataset();
        $dataset->columns()->where('name', 'kecamatan')->update(['label' => 'Nama Kecamatan']);
        $query = SavedQuery::factory()->for($dataset)->create(['config' => self::COUNT_PER_KECAMATAN]);
        $widget = Widget::factory()->for($query)->create([
            'chart_type' => 'bar',
            'options' => ['x' => 'kecamatan', 'y' => ['jumlah'], 'stacked' => false],
        ]);

        $this->actingAs($admin)
            ->getJson("/api/widgets/{$widget->id}/data")
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'jumlah' => 2]]);

        $this->travel(1)->seconds();
        $revision = $this->uploadRevision($dataset, "Kecamatan,Desa\nBangli,Kubu\nSusut,Abuan\nSusut,Sulahan\n");

        $this->post(route('data.revisions.confirm', $revision))->assertRedirect(route('data'));

        $dataset->refresh();
        $this->assertSame('done', $revision->refresh()->status);
        $this->assertSame(3, $dataset->row_count);
        $this->assertSame('baru.csv', $dataset->original_filename);
        $this->assertSame('Nama Kecamatan', $dataset->columns()->where('name', 'kecamatan')->value('label'));
        $this->assertFalse(Schema::hasTable($revision->stagingTable()));

        $this->getJson("/api/widgets/{$widget->id}/data")
            ->assertJsonPath('meta.cached', false)
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'jumlah' => 1], ['kecamatan' => 'Susut', 'jumlah' => 2]]);
    }

    public function test_failed_update_keeps_the_old_data(): void
    {
        Storage::fake('local');
        $dataset = $this->importedDataset();
        $revision = $this->uploadRevision($dataset, '[1, 2, 3]', 'rusak.json');

        // Queue `sync` di test menjalankan job langsung; di produksi job berjalan di worker.
        $this->post(route('data.revisions.confirm', $revision));

        $revision->refresh();
        $this->assertSame('failed', $revision->status);
        $this->assertSame('JSON harus berupa array of objects, contoh: [{"nama": "A"}, ...].', $revision->error_message);
        $this->assertSame(2, DB::table($dataset->refresh()->table_name)->count());
        $this->assertSame('awal.csv', $dataset->original_filename);
        $this->assertFalse(Schema::hasTable($revision->stagingTable()));
    }

    public function test_second_update_is_rejected_while_one_is_running(): void
    {
        Storage::fake('local');
        $dataset = $this->importedDataset();
        DatasetRevision::factory()->for($dataset)->create(['status' => 'processing']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/data/update', [
                'dataset_id' => $dataset->id,
                'file' => UploadedFile::fake()->createWithContent('baru.csv', "Kecamatan\nBangli\n"),
            ])
            ->assertJsonValidationErrors(['dataset_id' => 'Dataset ini sedang diperbarui. Tunggu sampai selesai.']);
    }

    public function test_dataset_cannot_be_deleted_while_update_runs(): void
    {
        Storage::fake('local');
        $dataset = $this->importedDataset();
        DatasetRevision::factory()->for($dataset)->create(['status' => 'pending']);

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('data'))
            ->delete(route('data.destroy', $dataset))
            ->assertSessionHas('error', 'Dataset sedang diimpor, tunggu sampai selesai.');

        $this->assertModelExists($dataset);
    }

    public function test_cancelling_removes_revision_and_file(): void
    {
        Storage::fake('local');
        $dataset = $this->importedDataset();
        $revision = $this->uploadRevision($dataset, "Kecamatan\nBangli\n");

        $this->delete(route('data.revisions.destroy', $revision))->assertRedirect(route('data.revisions.create'));

        $this->assertModelMissing($revision);
        Storage::disk('local')->assertMissing($revision->file_path);
    }

    public function test_update_page_offers_only_imported_datasets(): void
    {
        Dataset::factory()->create(['name' => 'Siap', 'table_name' => 'ds_1']);
        Dataset::factory()->create(['name' => 'Masih diproses', 'status' => 'processing']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('data.revisions.create'))
            ->assertOk()
            ->assertSee('Siap (')
            ->assertDontSee('Masih diproses');
    }

    private function importedDataset(): Dataset
    {
        Storage::disk('local')->put('uploads/awal.csv', "Kecamatan,Desa\nBangli,Kubu\nBangli,Cempaga\n");

        $dataset = Dataset::factory()->create([
            'original_filename' => 'awal.csv',
            'file_path' => 'uploads/awal.csv',
            'file_type' => 'csv',
            'status' => 'pending',
        ]);

        ImportDataset::dispatchSync($dataset);

        return $dataset->refresh();
    }

    private function uploadRevision(Dataset $dataset, string $contents, string $filename = 'baru.csv'): DatasetRevision
    {
        $this->actingAs(User::factory()->admin()->create())->postJson('/data/update', [
            'dataset_id' => $dataset->id,
            'file' => UploadedFile::fake()->createWithContent($filename, $contents),
        ])->assertCreated();

        return DatasetRevision::latest('id')->firstOrFail();
    }
}

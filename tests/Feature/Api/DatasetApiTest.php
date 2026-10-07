<?php

namespace Tests\Feature\Api;

use App\Jobs\ImportDataset;
use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/datasets')->assertUnauthorized();
    }

    public function test_viewer_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/datasets')
            ->assertForbidden();
    }

    public function test_index_lists_datasets_in_contract_format(): void
    {
        $this->freezeTime();
        $dataset = Dataset::factory()->create([
            'name' => 'Monitoring',
            'file_type' => 'csv',
            'status' => 'failed',
            'error_message' => 'File tidak berisi data.',
            'row_count' => 0,
            'column_count' => 0,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/datasets')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $dataset->id,
                    'name' => 'Monitoring',
                    'file_type' => 'csv',
                    'status' => 'failed',
                    'error_message' => 'File tidak berisi data.',
                    'row_count' => 0,
                    'column_count' => 0,
                    'created_at' => now()->toIso8601String(),
                ]],
            ]);
    }

    public function test_profile_returns_column_statistics(): void
    {
        $dataset = $this->importedDataset();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/datasets/{$dataset->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.row_count', 3)
            ->assertJsonPath('data.columns', [
                ['name' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'text', 'position' => 1, 'null_count' => 0, 'unique_count' => 2, 'min' => 'Abang', 'max' => 'Bangli'],
                ['name' => 'jumlah', 'label' => 'Jumlah', 'type' => 'integer', 'position' => 2, 'null_count' => 1, 'unique_count' => 2, 'min' => '5', 'max' => '12'],
            ]);
    }

    public function test_preview_returns_rows_as_objects(): void
    {
        $dataset = $this->importedDataset();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/datasets/{$dataset->id}/preview?limit=2")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'columns' => [
                        ['name' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'text'],
                        ['name' => 'jumlah', 'label' => 'Jumlah', 'type' => 'integer'],
                    ],
                    'rows' => [
                        ['kecamatan' => 'Bangli', 'jumlah' => 5],
                        ['kecamatan' => 'Abang', 'jumlah' => null],
                    ],
                ],
            ]);
    }

    public function test_preview_limit_above_100_is_rejected(): void
    {
        $dataset = $this->importedDataset();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/datasets/{$dataset->id}/preview?limit=101")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit' => 'The limit field must not be greater than 100.']);
    }

    public function test_profile_of_dataset_not_yet_imported_gets_409(): void
    {
        $dataset = Dataset::factory()->create(['status' => 'processing']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/datasets/{$dataset->id}/profile")
            ->assertConflict()
            ->assertJsonPath('message', 'Dataset belum selesai diimpor.');
    }

    private function importedDataset(): Dataset
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/data.csv', "Kecamatan,Jumlah\nBangli,5\nAbang,\nBangli,12\n");

        $dataset = Dataset::factory()->create([
            'file_path' => 'uploads/data.csv',
            'file_type' => 'csv',
            'status' => 'pending',
        ]);

        ImportDataset::dispatchSync($dataset);

        return $dataset->refresh();
    }
}

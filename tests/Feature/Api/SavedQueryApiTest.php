<?php

namespace Tests\Feature\Api;

use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesImportedDatasets;
use Tests\TestCase;

class SavedQueryApiTest extends TestCase
{
    use CreatesImportedDatasets;
    use RefreshDatabase;

    private const COUNT_PER_KECAMATAN = [
        'select' => ['kecamatan'],
        'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']],
        'group_by' => ['kecamatan'],
        'order_by' => [['column' => 'jumlah', 'dir' => 'desc'], ['column' => 'kecamatan', 'dir' => 'asc']],
    ];

    public function test_viewer_cannot_use_query_endpoints(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/queries')
            ->assertForbidden();
    }

    public function test_admin_can_save_query_and_config_is_normalized(): void
    {
        $admin = User::factory()->admin()->create();
        $dataset = $this->penduduk();

        $response = $this->actingAs($admin)->postJson('/api/queries', [
            'dataset_id' => $dataset->id,
            'name' => 'KRT per kecamatan',
            'description' => 'Jumlah KRT tiap kecamatan',
            'config' => self::COUNT_PER_KECAMATAN,
        ]);

        $savedQuery = SavedQuery::sole();
        $response->assertCreated()
            ->assertJsonPath('data.id', $savedQuery->id)
            ->assertJsonPath('data.dataset_name', $dataset->name);
        $this->assertSame([
            'select' => ['kecamatan'],
            'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']],
            'filters' => [],
            'group_by' => ['kecamatan'],
            'order_by' => [['column' => 'jumlah', 'dir' => 'desc'], ['column' => 'kecamatan', 'dir' => 'asc']],
            'limit' => 1000,
        ], $savedQuery->config);
        $this->assertSame($admin->id, $savedQuery->created_by);
    }

    public function test_invalid_config_is_rejected_with_config_prefixed_errors(): void
    {
        $dataset = $this->penduduk();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/queries', [
                'dataset_id' => $dataset->id,
                'name' => 'Salah',
                'config' => ['select' => ['password']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['config.select.0' => "Kolom 'password' tidak ada di dataset ini."]);

        $this->assertDatabaseCount('saved_queries', 0);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/queries', ['dataset_id' => $this->penduduk()->id, 'config' => []])
            ->assertJsonValidationErrors(['name' => 'Nama query wajib diisi.']);
    }

    public function test_admin_can_update_saved_query(): void
    {
        $dataset = $this->penduduk();
        $savedQuery = SavedQuery::factory()->for($dataset)->create(['config' => self::COUNT_PER_KECAMATAN]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/queries/{$savedQuery->id}", [
                'dataset_id' => $dataset->id,
                'name' => 'Nama baru',
                'config' => ['select' => ['kecamatan'], 'limit' => 5],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama baru');

        $this->assertSame(['kecamatan'], $savedQuery->refresh()->config['select']);
        $this->assertSame(5, $savedQuery->config['limit']);
    }

    public function test_saved_query_used_by_widget_cannot_be_deleted(): void
    {
        $widget = Widget::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/queries/{$widget->saved_query_id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Query masih dipakai widget dashboard.');

        $this->assertModelExists($widget->savedQuery);
    }

    public function test_unused_saved_query_can_be_deleted(): void
    {
        $savedQuery = SavedQuery::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/queries/{$savedQuery->id}")
            ->assertNoContent();

        $this->assertModelMissing($savedQuery);
    }

    public function test_ad_hoc_run_returns_result_in_contract_format(): void
    {
        $dataset = $this->penduduk();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/queries/run', ['dataset_id' => $dataset->id, 'config' => self::COUNT_PER_KECAMATAN])
            ->assertOk()
            ->assertJsonPath('data.columns', [
                ['name' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'text'],
                ['name' => 'jumlah', 'label' => 'Jumlah', 'type' => 'integer'],
            ])
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'jumlah' => 2], ['kecamatan' => 'Susut', 'jumlah' => 1]])
            ->assertJsonPath('meta.truncated', false)
            ->assertJsonPath('meta.cached', false);
    }

    public function test_second_run_is_served_from_cache(): void
    {
        $savedQuery = SavedQuery::factory()->for($this->penduduk())->create(['config' => self::COUNT_PER_KECAMATAN]);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson("/api/queries/{$savedQuery->id}/run")->assertJsonPath('meta.cached', false);

        DB::table($savedQuery->dataset->table_name)->insert(['kecamatan' => 'Susut']);

        $this->getJson("/api/queries/{$savedQuery->id}/run")
            ->assertJsonPath('meta.cached', true)
            ->assertJsonPath('data.rows.1.jumlah', 1);
    }

    public function test_cache_is_invalidated_when_dataset_changes(): void
    {
        $savedQuery = SavedQuery::factory()->for($this->penduduk())->create(['config' => self::COUNT_PER_KECAMATAN]);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson("/api/queries/{$savedQuery->id}/run")->assertJsonPath('meta.cached', false);

        $this->travel(1)->seconds();
        DB::table($savedQuery->dataset->table_name)->insert(['kecamatan' => 'Susut']);
        $savedQuery->dataset->touch();

        $this->getJson("/api/queries/{$savedQuery->id}/run")
            ->assertJsonPath('meta.cached', false)
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'jumlah' => 2], ['kecamatan' => 'Susut', 'jumlah' => 2]]);
    }

    public function test_running_query_on_dataset_not_yet_imported_gets_409(): void
    {
        $dataset = Dataset::factory()->create(['status' => 'processing']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/queries/run', ['dataset_id' => $dataset->id, 'config' => []])
            ->assertConflict();
    }

    private function penduduk(): Dataset
    {
        return $this->importedDataset(['kecamatan' => 'text'], [
            ['kecamatan' => 'Bangli'],
            ['kecamatan' => 'Bangli'],
            ['kecamatan' => 'Susut'],
        ]);
    }
}

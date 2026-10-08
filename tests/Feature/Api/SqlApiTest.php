<?php

namespace Tests\Feature\Api;

use App\Models\Dashboard;
use App\Models\QueryFolder;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesSqlFixtureDatabase;
use Tests\TestCase;

class SqlApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesSqlFixtureDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSqlFixture();

        parent::tearDown();
    }

    public function test_run_returns_result_in_query_format(): void
    {
        $this->sqlFixture();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/sql/run', ['sql' => 'SELECT kecamatan, SUM(jumlah_art) AS total FROM ds_1 GROUP BY kecamatan ORDER BY kecamatan'])
            ->assertOk()
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'total' => 7], ['kecamatan' => 'Susut', 'total' => 5]])
            ->assertJsonPath('data.columns.1', ['name' => 'total', 'label' => 'Total', 'type' => 'integer']);
    }

    public function test_run_rejects_reading_users_table(): void
    {
        $this->sqlFixture();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/sql/run', ['sql' => 'SELECT * FROM users'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sql' => "Tabel 'users' tidak boleh dibaca. Mode SQL hanya bisa membaca tabel dataset (ds_…)."]);
    }

    public function test_folders_nest_and_tree_lists_folders_and_files(): void
    {
        $this->sqlFixture();
        $this->actingAs(User::factory()->admin()->create());

        $parent = $this->postJson('/api/sql/folders', ['name' => 'Monitoring'])->assertCreated()->json('data.id');
        $child = $this->postJson('/api/sql/folders', ['name' => 'Per kecamatan', 'parent_id' => $parent])->assertCreated()->json('data.id');
        $this->postJson('/api/sql/files', ['name' => 'Jumlah', 'folder_id' => $child, 'sql' => 'SELECT COUNT(*) AS n FROM ds_1'])->assertCreated();

        $this->getJson('/api/sql/tree')
            ->assertOk()
            ->assertJsonPath('data.folders', [
                ['id' => $parent, 'name' => 'Monitoring', 'parent_id' => null],
                ['id' => $child, 'name' => 'Per kecamatan', 'parent_id' => $parent],
            ])
            ->assertJsonPath('data.files.0.name', 'Jumlah')
            ->assertJsonPath('data.files.0.folder_id', $child);
    }

    public function test_folder_cannot_be_moved_into_its_own_child(): void
    {
        $parent = QueryFolder::factory()->create();
        $child = QueryFolder::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/sql/folders/{$parent->id}", ['name' => $parent->name, 'parent_id' => $child->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Folder tidak bisa dipindah ke dalam dirinya sendiri.');

        $this->assertNull($parent->refresh()->parent_id);
    }

    public function test_folder_with_content_cannot_be_deleted(): void
    {
        $folder = QueryFolder::factory()->create();
        SavedQuery::factory()->sql()->create(['folder_id' => $folder->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/sql/folders/{$folder->id}")
            ->assertConflict();

        $this->assertModelExists($folder);
    }

    public function test_empty_folder_can_be_deleted(): void
    {
        $folder = QueryFolder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/sql/folders/{$folder->id}")
            ->assertNoContent();

        $this->assertModelMissing($folder);
    }

    public function test_file_with_invalid_sql_is_not_saved(): void
    {
        $this->sqlFixture();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/sql/files', ['name' => 'Rusak', 'sql' => 'DELETE FROM ds_1'])
            ->assertJsonValidationErrors(['sql' => 'Perintah ini tidak diizinkan. Mode SQL hanya untuk SELECT/WITH tanpa rekursi.']);

        $this->assertDatabaseCount('saved_queries', 0);
    }

    public function test_file_can_be_updated_and_moved(): void
    {
        $this->sqlFixture();
        $file = SavedQuery::factory()->sql()->create(['name' => 'Lama']);
        $folder = QueryFolder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/sql/files/{$file->id}", ['name' => 'Baru', 'folder_id' => $folder->id, 'sql' => 'SELECT kecamatan FROM ds_1'])
            ->assertOk()
            ->assertJsonPath('data.sql', 'SELECT kecamatan FROM ds_1');

        $file->refresh();
        $this->assertSame('Baru', $file->name);
        $this->assertSame($folder->id, $file->folder_id);
        $this->assertSame('sql', $file->type);
    }

    public function test_filter_data_query_is_not_reachable_as_sql_file(): void
    {
        $builder = SavedQuery::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/sql/files/{$builder->id}")
            ->assertNotFound();
    }

    public function test_file_used_by_widget_cannot_be_deleted(): void
    {
        $file = SavedQuery::factory()->sql()->create();
        Widget::factory()->create(['saved_query_id' => $file->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/sql/files/{$file->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'File SQL masih dipakai widget dashboard.');
    }

    public function test_sql_file_works_as_dashboard_widget_with_global_filter(): void
    {
        $this->sqlFixture();
        $admin = User::factory()->admin()->create();
        $file = SavedQuery::factory()->sql('SELECT kecamatan, status, COUNT(*) AS jumlah FROM ds_1 GROUP BY kecamatan, status ORDER BY kecamatan, status')->create();
        $dashboard = Dashboard::factory()->create([
            'filters' => [
                ['key' => 'status', 'label' => 'Status', 'type' => 'select'],
                ['key' => 'tanggal', 'label' => 'Periode', 'type' => 'date_range'],
            ],
        ]);

        $widgetId = $this->actingAs($admin)
            ->postJson("/api/dashboards/{$dashboard->id}/widgets", [
                'saved_query_id' => $file->id,
                'title' => 'Per kecamatan',
                'chart_type' => 'bar',
                'options' => ['x' => 'kecamatan', 'y' => ['jumlah']],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widgetId}/data?filters[status]=SUBMITTED&filters[tanggal][from]=2026-06-01")
            ->assertOk()
            ->assertJsonPath('data.rows', [
                ['kecamatan' => 'Bangli', 'status' => 'SUBMITTED', 'jumlah' => 1],
                ['kecamatan' => 'Susut', 'status' => 'SUBMITTED', 'jumlah' => 1],
            ])
            ->assertJsonPath('meta.filters_applied', ['status'])
            ->assertJsonPath('meta.filters_ignored', ['tanggal']);
    }

    public function test_sql_widget_options_must_reference_result_columns(): void
    {
        $this->sqlFixture();
        $file = SavedQuery::factory()->sql('SELECT kecamatan, COUNT(*) AS jumlah FROM ds_1 GROUP BY kecamatan')->create();
        $dashboard = Dashboard::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/dashboards/{$dashboard->id}/widgets", [
                'saved_query_id' => $file->id,
                'title' => 'Salah',
                'chart_type' => 'kpi',
                'options' => ['value' => 'total'],
            ])
            ->assertJsonValidationErrors(['options.value' => "Kolom 'total' tidak ada di hasil query."]);
    }
}

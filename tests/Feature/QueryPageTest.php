<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesImportedDatasets;
use Tests\TestCase;

class QueryPageTest extends TestCase
{
    use CreatesImportedDatasets;
    use RefreshDatabase;

    public function test_viewer_cannot_open_query_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('queries.builder'))
            ->assertForbidden();
    }

    public function test_opening_sql_file_in_builder_redirects_to_sql_page(): void
    {
        $file = SavedQuery::factory()->sql()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('queries.builder', ['query' => $file->id]))
            ->assertRedirect(route('sql.index', ['file' => $file->id]));
    }

    public function test_sql_page_lists_imported_dataset_tables_for_reference(): void
    {
        $dataset = $this->importedDataset(['kecamatan' => 'text'], [], ['name' => 'Pendataan']);
        Dataset::factory()->create(['name' => 'Belum Siap', 'status' => 'processing']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('sql.index'))
            ->assertOk()
            ->assertSee('data-insert="'.$dataset->table_name.'"', false)
            ->assertSee('data-insert="kecamatan"', false)
            ->assertDontSee('Belum Siap');
    }

    public function test_builder_offers_dashboards_for_add_to_dashboard(): void
    {
        $dashboard = Dashboard::factory()->create(['name' => 'Monitoring', 'slug' => 'monitoring']);
        $this->importedDataset(['kecamatan' => 'text'], []);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('queries.builder'))
            ->assertOk()
            ->assertSee('<option value="'.$dashboard->id.'" data-slug="monitoring">Monitoring</option>', false)
            ->assertSee('Tampilkan di dashboard');
    }

    public function test_builder_offers_only_imported_datasets(): void
    {
        $imported = $this->importedDataset(['kecamatan' => 'text'], [], ['name' => 'Siap Dipakai']);
        Dataset::factory()->create(['name' => 'Masih Diproses', 'status' => 'processing']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('queries.builder'))
            ->assertOk()
            ->assertSee('<option value="'.$imported->id.'">Siap Dipakai</option>', false)
            ->assertDontSee('Masih Diproses');
    }

    public function test_builder_in_edit_mode_embeds_the_saved_query(): void
    {
        $dataset = $this->importedDataset(['kecamatan' => 'text'], []);
        $savedQuery = SavedQuery::factory()->for($dataset)->create(['name' => "Query 'kutip'"]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('queries.builder', ['query' => $savedQuery->id]))
            ->assertOk()
            ->assertSee('Edit: Query &#039;kutip&#039;', false)
            ->assertSee('"name":"Query \\u0027kutip\\u0027"', false);
    }
}

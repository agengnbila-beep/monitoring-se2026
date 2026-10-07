<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
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

    public function test_list_shows_saved_queries_with_their_dataset(): void
    {
        $savedQuery = SavedQuery::factory()->create(['name' => 'KRT per kecamatan']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('queries.index'))
            ->assertOk()
            ->assertSeeInOrder(['KRT per kecamatan', $savedQuery->dataset->name]);
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

    public function test_admin_can_delete_unused_query(): void
    {
        $savedQuery = SavedQuery::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('queries.destroy', $savedQuery))
            ->assertRedirect(route('queries.index'))
            ->assertSessionHas('success', 'Query dihapus.');

        $this->assertModelMissing($savedQuery);
    }

    public function test_query_used_by_widget_is_not_deleted(): void
    {
        $widget = Widget::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('queries.index'))
            ->delete(route('queries.destroy', $widget->saved_query_id))
            ->assertRedirect(route('queries.index'))
            ->assertSessionHas('error', 'Query masih dipakai widget dashboard.');

        $this->assertModelExists($widget->savedQuery);
    }
}

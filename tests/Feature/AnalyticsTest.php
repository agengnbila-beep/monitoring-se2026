<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\QueryFolder;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_page_lists_subfolders_and_both_file_types(): void
    {
        $folder = QueryFolder::factory()->create(['name' => 'Monitoring']);
        QueryFolder::factory()->create(['name' => 'Per kecamatan', 'parent_id' => $folder->id]);
        $filter = SavedQuery::factory()->create(['name' => 'Ringkasan status', 'folder_id' => $folder->id]);
        $sql = SavedQuery::factory()->sql()->create(['name' => 'Query progres', 'folder_id' => $folder->id]);
        SavedQuery::factory()->create(['name' => 'Di akar']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('analitik.index', ['folder' => $folder->id]))
            ->assertOk()
            ->assertSeeInOrder(['Analitik', 'Monitoring'])
            ->assertSee('📁 Per kecamatan')
            ->assertSee('href="'.route('queries.builder', ['query' => $filter->id]).'"', false)
            ->assertSee('href="'.route('sql.index', ['file' => $sql->id]).'"', false)
            ->assertDontSee('Di akar');
    }

    public function test_new_file_links_carry_the_current_folder(): void
    {
        $folder = QueryFolder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('analitik.index', ['folder' => $folder->id]))
            ->assertSee('href="'.route('queries.builder', ['folder' => $folder->id]).'"', false)
            ->assertSee('href="'.route('sql.index', ['folder' => $folder->id]).'"', false);
    }

    public function test_old_filter_data_list_redirects_to_analytics(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/queries')
            ->assertRedirect('/analitik');
    }

    public function test_viewer_cannot_open_analytics(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('analitik.index'))
            ->assertForbidden();
    }

    public function test_folders_can_nest(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $parent = $this->postJson('/api/analitik/folders', ['name' => 'Monitoring'])->assertCreated()->json('data.id');
        $this->postJson('/api/analitik/folders', ['name' => 'Per kecamatan', 'parent_id' => $parent])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parent);
    }

    public function test_folder_cannot_be_moved_into_its_own_child(): void
    {
        $parent = QueryFolder::factory()->create();
        $child = QueryFolder::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/analitik/folders/{$parent->id}", ['name' => $parent->name, 'parent_id' => $child->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Folder tidak bisa dipindah ke dalam dirinya sendiri.');

        $this->assertNull($parent->refresh()->parent_id);
    }

    public function test_folder_with_content_cannot_be_deleted(): void
    {
        $folder = QueryFolder::factory()->create();
        SavedQuery::factory()->create(['folder_id' => $folder->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/analitik/folders/{$folder->id}")
            ->assertConflict();

        $this->assertModelExists($folder);
    }

    public function test_empty_folder_can_be_deleted(): void
    {
        $folder = QueryFolder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/analitik/folders/{$folder->id}")
            ->assertNoContent();

        $this->assertModelMissing($folder);
    }

    public function test_any_file_type_can_be_moved_between_folders(): void
    {
        $folder = QueryFolder::factory()->create();
        $filter = SavedQuery::factory()->create();
        $sql = SavedQuery::factory()->sql()->create(['folder_id' => $folder->id]);
        $this->actingAs(User::factory()->admin()->create());

        $this->putJson("/api/analitik/files/{$filter->id}/folder", ['folder_id' => $folder->id])->assertOk();
        $this->putJson("/api/analitik/files/{$sql->id}/folder", ['folder_id' => null])->assertOk();

        $this->assertSame($folder->id, $filter->refresh()->folder_id);
        $this->assertNull($sql->refresh()->folder_id);
    }

    public function test_file_used_by_widget_cannot_be_deleted(): void
    {
        $widget = Widget::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/analitik/files/{$widget->saved_query_id}")
            ->assertConflict()
            ->assertJsonPath('message', 'File masih dipakai widget dashboard.');

        $this->assertModelExists($widget->savedQuery);
    }

    public function test_unused_file_can_be_deleted(): void
    {
        $file = SavedQuery::factory()->sql()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/analitik/files/{$file->id}")
            ->assertNoContent();

        $this->assertModelMissing($file);
    }

    public function test_filter_data_is_saved_into_a_folder(): void
    {
        $folder = QueryFolder::factory()->create();
        $dataset = Dataset::factory()->create(['table_name' => 'ds_1']);
        Schema::create('ds_1', fn ($t) => $t->id('_row_id'));

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/queries', ['dataset_id' => $dataset->id, 'name' => 'Ringkasan', 'folder_id' => $folder->id, 'config' => []])
            ->assertCreated()
            ->assertJsonPath('data.folder_id', $folder->id);
    }
}

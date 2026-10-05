<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetadataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_dataset_also_deletes_its_columns(): void
    {
        $dataset = Dataset::factory()->create();
        DatasetColumn::factory()->count(3)->for($dataset)->create();

        $dataset->delete();

        $this->assertDatabaseCount('dataset_columns', 0);
    }

    public function test_dataset_used_by_saved_query_cannot_be_deleted(): void
    {
        $query = SavedQuery::factory()->create();

        $this->expectException(QueryException::class);

        $query->dataset->delete();
    }

    public function test_deleting_dashboard_deletes_widgets_but_keeps_saved_query(): void
    {
        $widget = Widget::factory()->create();

        $widget->dashboard->delete();

        $this->assertModelMissing($widget);
        $this->assertDatabaseCount('saved_queries', 1);
        $this->assertModelMissing($widget);
        $this->assertDatabaseCount('saved_queries', 1);
    }

    public function test_saved_query_used_by_widget_cannot_be_deleted(): void
    {
        $widget = Widget::factory()->create();

        $this->expectException(QueryException::class);

        $widget->savedQuery->delete();
    }

    public function test_columns_are_ordered_by_position(): void
    {
        $dataset = Dataset::factory()->create();
        DatasetColumn::factory()->for($dataset)->create(['position' => 2, 'name' => 'desa']);
        DatasetColumn::factory()->for($dataset)->create(['position' => 1, 'name' => 'kecamatan']);

        $this->assertSame(['kecamatan', 'desa'], $dataset->columns()->pluck('name')->all());
    }

    public function test_json_config_is_cast_to_array(): void
    {
        $query = SavedQuery::factory()->create(['config' => ['limit' => 50]]);

        $this->assertSame(50, $query->fresh()->config['limit']);
    }

    public function test_new_user_is_viewer_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame('viewer', $user->fresh()->role);
        $this->assertFalse($user->fresh()->isAdmin());
    }
}

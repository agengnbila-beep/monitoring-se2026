<?php

namespace Tests\Feature\Api;

use App\Models\Dashboard;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesImportedDatasets;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use CreatesImportedDatasets;
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/dashboards')->assertUnauthorized();
    }

    public function test_viewer_cannot_create_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/dashboards', ['name' => 'Monitoring'])
            ->assertForbidden();
    }

    public function test_admin_creates_dashboard_with_unique_slug(): void
    {
        $admin = User::factory()->admin()->create();
        Dashboard::factory()->create(['slug' => 'monitoring-se2026']);

        $this->actingAs($admin)
            ->postJson('/api/dashboards', [
                'name' => 'Monitoring SE2026',
                'filters' => [['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'select']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'monitoring-se2026-2')
            ->assertJsonPath('data.filters', [['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'select']])
            ->assertJsonPath('data.widgets', []);
    }

    public function test_dashboard_name_is_required(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/dashboards', [])
            ->assertJsonValidationErrors(['name' => 'Nama dashboard wajib diisi.']);
    }

    public function test_admin_adds_widget_and_options_are_normalized(): void
    {
        $dashboard = Dashboard::factory()->create();
        $savedQuery = $this->countPerKecamatan();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/dashboards/{$dashboard->id}/widgets", [
                'saved_query_id' => $savedQuery->id,
                'title' => 'KRT per kecamatan',
                'chart_type' => 'bar',
                'options' => ['x' => 'kecamatan', 'y' => ['jumlah'], 'warna' => 'merah'],
                'width' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('data.options', ['x' => 'kecamatan', 'y' => ['jumlah'], 'stacked' => false]);

        $this->assertSame(12, Widget::sole()->width);
    }

    public function test_widget_option_must_reference_a_query_result_column(): void
    {
        $dashboard = Dashboard::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/dashboards/{$dashboard->id}/widgets", [
                'saved_query_id' => $this->countPerKecamatan()->id,
                'title' => 'Salah',
                'chart_type' => 'pie',
                'options' => ['label' => 'desa', 'value' => 'jumlah'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options.label' => "Kolom 'desa' tidak ada di hasil query."]);

        $this->assertDatabaseCount('widgets', 0);
    }

    public function test_unknown_chart_type_is_rejected(): void
    {
        $dashboard = Dashboard::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/dashboards/{$dashboard->id}/widgets", [
                'saved_query_id' => $this->countPerKecamatan()->id,
                'title' => 'Peta',
                'chart_type' => 'map',
                'options' => [],
            ])
            ->assertJsonValidationErrors(['chart_type' => 'Jenis chart harus salah satu dari: kpi, bar, line, pie, table.']);
    }

    public function test_viewer_loads_dashboard_with_widgets_in_order(): void
    {
        $dashboard = Dashboard::factory()->create();
        $second = Widget::factory()->for($dashboard)->create(['title' => 'Kedua', 'sort_order' => 2]);
        $first = Widget::factory()->for($dashboard)->create(['title' => 'Pertama', 'sort_order' => 1]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/dashboards/{$dashboard->id}")
            ->assertOk()
            ->assertJsonPath('data.widgets.0.id', $first->id)
            ->assertJsonPath('data.widgets.1.id', $second->id)
            ->assertJsonPath('data.widgets.0.data_url', route('api.widgets.data', $first));
    }

    public function test_viewer_gets_widget_data_from_its_saved_query(): void
    {
        $widget = Widget::factory()->create([
            'saved_query_id' => $this->countPerKecamatan()->id,
            'chart_type' => 'bar',
            'options' => ['x' => 'kecamatan', 'y' => ['jumlah'], 'stacked' => false],
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widget->id}/data")
            ->assertOk()
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'jumlah' => 2], ['kecamatan' => 'Susut', 'jumlah' => 1]]);
    }

    public function test_admin_updates_widget(): void
    {
        $widget = Widget::factory()->create(['saved_query_id' => $this->countPerKecamatan()->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/widgets/{$widget->id}", [
                'saved_query_id' => $widget->saved_query_id,
                'title' => 'Total KRT',
                'chart_type' => 'kpi',
                'options' => ['value' => 'jumlah'],
                'sort_order' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.options', ['value' => 'jumlah', 'format' => 'number']);

        $widget->refresh();
        $this->assertSame('Total KRT', $widget->title);
        $this->assertSame(3, $widget->sort_order);
    }

    public function test_widgets_are_reordered_in_one_request(): void
    {
        $dashboard = Dashboard::factory()->create();
        [$a, $b, $c] = Widget::factory()->for($dashboard)->count(3)->sequence(['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3])->create();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/dashboards/{$dashboard->id}/widgets/order", ['order' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertSame([$c->id, $a->id, $b->id], $dashboard->widgets()->pluck('id')->all());
    }

    public function test_reorder_must_contain_exactly_the_dashboards_widgets(): void
    {
        $dashboard = Dashboard::factory()->create();
        [$a, $b] = Widget::factory()->for($dashboard)->count(2)->create();
        $other = Widget::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->putJson("/api/dashboards/{$dashboard->id}/widgets/order", ['order' => [$a->id]])
            ->assertJsonValidationErrors(['order' => 'Urutan harus memuat semua widget dashboard.']);

        $this->putJson("/api/dashboards/{$dashboard->id}/widgets/order", ['order' => [$a->id, $other->id]])
            ->assertJsonValidationErrors(['order.1' => 'Widget tidak ada di dashboard ini.']);
    }

    public function test_deleting_dashboard_removes_its_widgets(): void
    {
        $widget = Widget::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/dashboards/{$widget->dashboard_id}")
            ->assertNoContent();

        $this->assertModelMissing($widget);
        $this->assertModelExists($widget->savedQuery);
    }

    private function countPerKecamatan(): SavedQuery
    {
        $dataset = $this->importedDataset(['kecamatan' => 'text'], [
            ['kecamatan' => 'Bangli'],
            ['kecamatan' => 'Bangli'],
            ['kecamatan' => 'Susut'],
        ]);

        return SavedQuery::factory()->for($dataset)->create([
            'config' => [
                'select' => ['kecamatan'],
                'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']],
                'filters' => [],
                'group_by' => ['kecamatan'],
                'order_by' => [['column' => 'jumlah', 'dir' => 'desc']],
                'limit' => 100,
            ],
        ]);
    }
}

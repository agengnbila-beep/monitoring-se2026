<?php

namespace Tests\Feature\Api;

use App\Models\Dashboard;
use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesImportedDatasets;
use Tests\TestCase;

class DashboardFilterTest extends TestCase
{
    use CreatesImportedDatasets;
    use RefreshDatabase;

    public function test_select_filter_narrows_widget_data(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widget->id}/data?filters[kecamatan]=Bangli")
            ->assertOk()
            ->assertJsonPath('data.rows', [['jumlah' => 2]])
            ->assertJsonPath('meta.filters_applied', ['kecamatan'])
            ->assertJsonPath('meta.filters_ignored', []);
    }

    public function test_date_range_filter_includes_both_ends(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widget->id}/data?filters[tanggal][from]=2026-06-02&filters[tanggal][to]=2026-06-03")
            ->assertOk()
            ->assertJsonPath('data.rows', [['jumlah' => 2]]);
    }

    public function test_date_range_with_only_start_date(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widget->id}/data?filters[tanggal][from]=2026-06-03")
            ->assertJsonPath('data.rows', [['jumlah' => 1]]);
    }

    public function test_filter_is_ignored_for_widget_whose_dataset_lacks_the_column(): void
    {
        $wilayah = $this->importedDataset(['desa' => 'text'], [['desa' => 'Kubu'], ['desa' => 'Abuan']]);
        $widget = $this->widgetOn($wilayah);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widget->id}/data?filters[kecamatan]=Bangli")
            ->assertOk()
            ->assertJsonPath('data.rows', [['jumlah' => 2]])
            ->assertJsonPath('meta.filters_ignored', ['kecamatan']);
    }

    public function test_keys_not_defined_on_the_dashboard_are_ignored(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widget->id}/data?filters[status]=OPEN")
            ->assertOk()
            ->assertJsonPath('data.rows', [['jumlah' => 3]])
            ->assertJsonPath('meta.filters_applied', []);
    }

    public function test_invalid_date_is_rejected(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/widgets/{$widget->id}/data?filters[tanggal][from]=03-06-2026")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tanggal.from' => 'Tanggal awal harus berformat YYYY-MM-DD.']);
    }

    public function test_filter_value_is_bound_not_injected(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson('/api/widgets/'.$widget->id.'/data?filters[kecamatan]='.urlencode("x' OR '1'='1"))
            ->assertOk()
            ->assertJsonPath('data.rows', [['jumlah' => 0]]);
    }

    public function test_select_options_are_distinct_values_from_widget_datasets(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/dashboards/{$widget->dashboard_id}/filters/kecamatan/options")
            ->assertOk()
            ->assertExactJson(['data' => ['Bangli', 'Susut']]);
    }

    public function test_options_for_undefined_filter_key_is_404(): void
    {
        $widget = $this->widgetOn($this->penduduk());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/dashboards/{$widget->dashboard_id}/filters/password/options")
            ->assertNotFound();
    }

    private function penduduk(): Dataset
    {
        return $this->importedDataset(['kecamatan' => 'text', 'tanggal' => 'date'], [
            ['kecamatan' => 'Bangli', 'tanggal' => '2026-06-01'],
            ['kecamatan' => 'Bangli', 'tanggal' => '2026-06-02'],
            ['kecamatan' => 'Susut', 'tanggal' => '2026-06-03'],
        ]);
    }

    private function widgetOn(Dataset $dataset): Widget
    {
        $dashboard = Dashboard::factory()->create([
            'filters' => [
                ['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'select'],
                ['key' => 'tanggal', 'label' => 'Periode', 'type' => 'date_range'],
            ],
        ]);

        $savedQuery = SavedQuery::factory()->for($dataset)->create([
            'config' => [
                'select' => [],
                'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']],
                'filters' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 100,
            ],
        ]);

        return Widget::factory()->for($dashboard)->for($savedQuery)->create([
            'chart_type' => 'kpi',
            'options' => ['value' => 'jumlah', 'format' => 'number'],
        ]);
    }
}

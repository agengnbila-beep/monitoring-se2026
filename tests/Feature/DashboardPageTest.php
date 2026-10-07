<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_sees_first_dashboard_with_widget_payload(): void
    {
        $dashboard = Dashboard::factory()->create(['name' => 'Monitoring SE2026']);
        $widget = Widget::factory()->for($dashboard)->create(['title' => 'KRT tersubmit']);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<h1>Monitoring SE2026</h1>', false)
            ->assertSee('"title":"KRT tersubmit"', false)
            ->assertSee('"data_url":"'.str_replace('/', '\/', route('api.widgets.data', $widget)).'"', false);
    }

    public function test_admin_gets_editor_controls(): void
    {
        $dashboard = Dashboard::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('id="dashEditToggle"', false)
            ->assertSee('data-can-edit="1"', false)
            ->assertSee('data-widget-store-url="'.route('api.widgets.store', $dashboard).'"', false);
    }

    public function test_viewer_gets_no_editor_controls(): void
    {
        Dashboard::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('dashEditToggle')
            ->assertDontSee('data-can-edit')
            ->assertDontSee('dashCreate');
    }

    public function test_dashboard_is_opened_by_slug(): void
    {
        Dashboard::factory()->create(['name' => 'A Dashboard']);
        Dashboard::factory()->create(['name' => 'Wilayah', 'slug' => 'wilayah']);

        $this->actingAs(User::factory()->create())
            ->get('/dashboards/wilayah')
            ->assertOk()
            ->assertSee('<h1>Wilayah</h1>', false)
            ->assertSee('Pilih dashboard');
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboards/tidak-ada')
            ->assertNotFound();
    }

    public function test_empty_state_when_no_dashboard_exists(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Belum ada dashboard');
    }

    public function test_dashboard_name_in_payload_cannot_break_out_of_attribute(): void
    {
        Dashboard::factory()->create(['name' => "x' onmouseover='alert(1)"]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee("x' onmouseover='alert(1)", false);
    }
}

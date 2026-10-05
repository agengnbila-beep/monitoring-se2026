<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_page_loads(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_data_page_lists_datasets(): void
    {
        Dataset::factory()->create(['name' => 'Dataset Uji']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/data')
            ->assertOk()
            ->assertSee('Dataset Uji');
    }
}

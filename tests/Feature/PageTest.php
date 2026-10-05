<?php

namespace Tests\Feature;

use App\Models\Dataset;
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
        $this->get('/dashboard')->assertOk();
    }

    public function test_data_page_lists_datasets(): void
    {
        Dataset::create([
            'name' => 'Dataset Uji',
            'original_filename' => 'uji.xlsx',
            'file_type' => 'xlsx',
            'row_count' => 100,
            'column_count' => 5,
            'status' => 'done',
        ]);

        $this->get('/data')
            ->assertOk()
            ->assertSee('Dataset Uji');
    }
}

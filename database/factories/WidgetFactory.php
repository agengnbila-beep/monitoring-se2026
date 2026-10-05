<?php

namespace Database\Factories;

use App\Models\Dashboard;
use App\Models\SavedQuery;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dashboard_id' => Dashboard::factory(),
            'saved_query_id' => SavedQuery::factory(),
            'title' => fake()->sentence(2),
            'chart_type' => 'kpi',
            'options' => ['value' => 'jumlah', 'format' => 'number'],
        ];
    }
}

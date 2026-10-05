<?php

namespace Database\Factories;

use App\Models\Dataset;
use App\Models\SavedQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedQuery>
 */
class SavedQueryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_id' => Dataset::factory(),
            'name' => fake()->sentence(3),
            'config' => [
                'select' => [],
                'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'jumlah']],
                'filters' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 100,
            ],
        ];

    }
}

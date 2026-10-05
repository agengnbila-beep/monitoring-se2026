<?php

namespace Database\Factories;

use App\models\Dataset;
use App\Models\DatasetColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetColumn>
 */
class DatasetColumnFactory extends Factory
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
            'position' => fake()->numberBetween(1, 20),
            'name' => fake()->unique()->lexify('kolom_????'),
            'label' => fn (array $attributes) => $attributes['name'],
            'type' => 'text',
        ];
    }
}

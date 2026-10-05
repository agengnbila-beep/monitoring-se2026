<?php

namespace Database\Factories;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dataset>
 */
class DatasetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'original_filename' => fake()->slug(2).'.csv',
            'file_type' => 'csv',
            'file_size' => fake()->numberBetween(1_000, 5_000_000),
            'status' => 'done',
            'row_count' => fake()->numberBetween(10, 1_000),
            'column_count' => 3,
        ];
    }
}

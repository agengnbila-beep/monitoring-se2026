<?php

namespace Database\Factories;

use App\Models\Dataset;
use App\Models\DatasetRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetRevision>
 */
class DatasetRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_id' => Dataset::factory(),
            'original_filename' => fake()->slug(2).'.csv',
            'file_path' => 'uploads/'.fake()->uuid().'.csv',
            'file_type' => 'csv',
            'file_size' => fake()->numberBetween(100, 10_000),
        ];
    }
}

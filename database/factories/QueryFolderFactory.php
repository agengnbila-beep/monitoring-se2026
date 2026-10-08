<?php

namespace Database\Factories;

use App\Models\QueryFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueryFolder>
 */
class QueryFolderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
        ];
    }
}

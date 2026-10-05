<?php

namespace Database\Factories;

use App\Models\CannedJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CannedJob>
 */
class CannedJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\CustomWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomWorkflow>
 */
class CustomWorkflowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'value' => CustomWorkflow::valueForNew(),
        ];
    }
}

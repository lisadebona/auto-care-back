<?php

namespace Database\Factories;

use App\Enums\EstimateItemType;
use App\Models\CannedJob;
use App\Models\CannedJobLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CannedJobLineItem>
 */
class CannedJobLineItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'canned_job_id' => CannedJob::factory(),
            'type' => EstimateItemType::Part,
            'description' => fake()->words(3, true),
            'price' => fake()->randomFloat(2, 1, 100),
            'quantity' => 1,
            'discount' => null,
            'remarks' => null,
            'sort_order' => 0,
        ];
    }
}

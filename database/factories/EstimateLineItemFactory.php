<?php

namespace Database\Factories;

use App\Enums\EstimateItemType;
use App\Models\EstimateLineItem;
use App\Models\EstimateService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateLineItem>
 */
class EstimateLineItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_service_id' => EstimateService::factory(),
            'type' => EstimateItemType::Part,
            'description' => fake()->optional()->words(3, true),
            'price' => fake()->randomFloat(2, 0, 500),
            'quantity' => 1,
            'discount' => null,
            'status' => null,
            'sort_order' => 0,
        ];
    }
}

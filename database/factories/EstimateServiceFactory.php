<?php

namespace Database\Factories;

use App\Models\Estimate;
use App\Models\EstimateService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateService>
 */
class EstimateServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_id' => Estimate::factory(),
            'name' => fake()->optional()->sentence(3),
            'notes' => null,
            'authorized' => false,
            'discount_percent' => 0,
            'epa_percent' => 7,
            'shop_supplies_percent' => 7,
            'tax_percent' => 7,
            'sort_order' => 0,
        ];
    }
}

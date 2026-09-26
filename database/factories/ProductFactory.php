<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'unit_price' => fake()->randomFloat(2, 1, 500),
            'margin' => 20,
            'stock_quantity' => fake()->numberBetween(0, 50),
            'category_id' => Category::factory(),
            'upc_code' => fake()->optional()->ean13(),
            'part_number' => fake()->optional()->bothify('??-####'),
            'is_taxable' => true,
        ];
    }
}

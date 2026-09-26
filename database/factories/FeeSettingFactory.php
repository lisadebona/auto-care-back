<?php

namespace Database\Factories;

use App\Enums\FeeType;
use App\Enums\ShopSuppliesCap;
use App\Models\FeeSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeSetting>
 */
class FeeSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_supplies_cap' => ShopSuppliesCap::NoCap,
            'shop_supplies_cap_amount' => null,
            'shop_supplies_fee' => 3,
            'shop_supplies_fee_type' => FeeType::Percent,
            'shop_supplies_on_parts' => true,
            'shop_supplies_on_labor' => false,
            'epa_rate' => 2,
            'epa_on_parts' => true,
            'epa_on_labor' => false,
            'tax_rate' => 7,
            'tax_on_parts' => true,
            'tax_on_labor' => true,
            'tax_on_epa' => false,
            'tax_on_shop_supplies' => false,
            'tax_on_subcontract' => false,
        ];
    }

    /**
     * Indicate that shop supplies are capped per order.
     */
    public function orderCap(string $amount = '50.00'): static
    {
        return $this->state(fn (array $attributes): array => [
            'shop_supplies_cap' => ShopSuppliesCap::OrderCap,
            'shop_supplies_cap_amount' => $amount,
        ]);
    }
}

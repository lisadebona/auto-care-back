<?php

namespace Database\Factories;

use App\Models\GeneralSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneralSetting>
 */
class GeneralSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'website' => fake()->url(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('(###) ###-####'),
            'timezone' => 'America/New_York',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['CA', 'FL', 'IL', 'NY', 'TX']),
            'zip_code' => fake()->postcode(),
            'country' => 'United States',
        ];
    }
}

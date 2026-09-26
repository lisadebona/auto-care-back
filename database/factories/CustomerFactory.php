<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'home_address' => fake()->streetAddress(),
            'home_city' => fake()->city(),
            'home_state' => fake()->randomElement(['CA', 'FL', 'IL', 'NY', 'TX']),
            'home_zip_code' => fake()->postcode(),
            'home_country' => Customer::DEFAULT_COUNTRY,
            'office_address' => fake()->optional()->streetAddress(),
            'office_city' => fake()->optional()->city(),
            'office_state' => fake()->optional()->randomElement(['CA', 'FL', 'IL', 'NY', 'TX']),
            'office_zip_code' => fake()->optional()->postcode(),
            'office_country' => Customer::DEFAULT_COUNTRY,
            'phone' => fake()->numerify('(###) ###-####'),
            'phone_2' => fake()->optional()->numerify('(###) ###-####'),
            'email' => fake()->unique()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

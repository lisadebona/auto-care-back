<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'year' => fake()->numberBetween(1995, (int) date('Y') + 1),
            'make' => fake()->randomElement(['Nissan', 'Toyota', 'Honda', 'Ford', 'Chevrolet']),
            'model' => fake()->randomElement(['Pathfinder', 'Camry', 'Civic', 'F-150', 'Silverado']),
            'sub_model' => fake()->optional()->randomElement(['LE', 'SE', 'XLT', 'LT', 'Sport']),
            'transmission' => fake()->optional()->randomElement(Vehicle::TRANSMISSIONS),
            'engine_size' => fake()->optional()->randomElement(['2.5L I4', '3.5L V6', '4.0L V6', '5.0L V8']),
            'drivetrain' => fake()->optional()->randomElement(Vehicle::DRIVETRAINS),
            'type' => fake()->randomElement(Vehicle::TYPES),
            'mileage' => fake()->optional()->numberBetween(0, 250000),
            'vin' => fake()->optional()->bothify(str_repeat('?', 17)),
            'image_path' => null,
        ];
    }
}

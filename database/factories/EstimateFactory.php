<?php

namespace Database\Factories;

use App\Enums\EstimateOrderStatus;
use App\Enums\EstimateWorkflow;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1000, 99999),
            'customer_id' => Customer::factory(),
            'vehicle_id' => null,
            'service_writer_id' => User::factory(),
            'due_date' => null,
            'customer_comments' => null,
            'recommendations' => null,
            'po_number' => null,
            'completed_at' => null,
            'payment_terms' => Estimate::DEFAULT_PAYMENT_TERMS,
            'order_status' => EstimateOrderStatus::Estimate,
            'workflow' => EstimateWorkflow::Estimates,
            'authorized_at' => null,
            'labels' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'customer_id' => $customer->id,
        ]);
    }

    public function withVehicle(?Vehicle $vehicle = null): static
    {
        return $this->state(function () use ($vehicle): array {
            $vehicle ??= Vehicle::factory()->create();

            return [
                'customer_id' => $vehicle->customer_id,
                'vehicle_id' => $vehicle->id,
            ];
        });
    }
}

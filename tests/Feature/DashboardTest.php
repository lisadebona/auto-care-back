<?php

namespace Tests\Feature;

use App\Enums\EstimateOrderStatus;
use App\Enums\EstimateWorkflow;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_the_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_users_without_estimate_access_cannot_view_the_dashboard(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_dashboard_returns_five_most_recent_records_for_each_workflow(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('estimates.view');
        $customer = Customer::factory()->create([
            'first_name' => 'Margaret',
            'last_name' => 'Garcia',
        ]);
        $vehicle = Vehicle::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2011,
            'make' => 'Toyota',
            'model' => '4Runner',
            'sub_model' => 'Limited',
        ]);

        foreach (range(1, 6) as $offset) {
            $this->record([
                'number' => 1000 + $offset,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'workflow' => EstimateWorkflow::Estimates->value,
            ], $offset);
        }

        $this->record([
            'number' => 2000,
            'customer_id' => $customer->id,
            'workflow' => EstimateWorkflow::DroppedOff->value,
        ], 1);
        $this->record([
            'number' => 3000,
            'customer_id' => $customer->id,
            'workflow' => EstimateWorkflow::InProgress->value,
            'order_number' => '4001',
        ], 1);
        $this->record([
            'number' => 4000,
            'customer_id' => $customer->id,
            'order_status' => EstimateOrderStatus::Invoice,
            'workflow' => EstimateWorkflow::Invoices->value,
            'invoice_number' => '1001',
        ], 1);
        $this->record([
            'number' => 9000,
            'customer_id' => $customer->id,
            'workflow' => EstimateWorkflow::Cancelled->value,
        ], 1);

        $response = $this->actingAs($actor)->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('workflows.0.label', 'Estimates')
            ->assertJsonCount(5, 'workflows.0.items')
            ->assertJsonPath('workflows.0.items.0.reference', '#1001')
            ->assertJsonPath('workflows.0.items.0.customer', 'Margaret Garcia')
            ->assertJsonPath('workflows.0.items.0.vehicle', '2011 Toyota 4Runner Limited')
            ->assertJsonPath('workflows.0.items.0.total', '0.00')
            ->assertJsonPath('workflows.0.items.4.reference', '#1005')
            ->assertJsonMissing(['reference' => '#1006'])
            ->assertJsonPath('workflows.1.label', 'Dropped Off')
            ->assertJsonPath('workflows.1.items.0.reference', '#2000')
            ->assertJsonPath('workflows.2.label', 'In Progress')
            ->assertJsonPath('workflows.2.items.0.reference', '4001')
            ->assertJsonPath('workflows.3.label', 'Invoices')
            ->assertJsonPath('workflows.3.items.0.reference', '1001')
            ->assertJsonMissing(['reference' => '#9000']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes, int $minutesAgo): void
    {
        $estimate = Estimate::factory()->create($attributes);

        $estimate->newQuery()->whereKey($estimate->id)->update([
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_manage_estimates(): void
    {
        $estimate = Estimate::factory()->create();

        $this->getJson('/api/estimates')->assertForbidden();
        $this->postJson('/api/estimates', [])->assertForbidden();
        $this->getJson("/api/estimates/{$estimate->id}")->assertForbidden();
        $this->putJson("/api/estimates/{$estimate->id}", [])->assertForbidden();
        $this->deleteJson("/api/estimates/{$estimate->id}")->assertForbidden();
    }

    public function test_authorized_user_can_create_update_and_delete_an_estimate(): void
    {
        $actor = $this->estimateUser([
            'estimates.view',
            'estimates.create',
            'estimates.edit',
            'estimates.delete',
        ]);
        $customer = Customer::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        $vehicle = Vehicle::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2018,
            'make' => 'Nissan',
            'model' => 'Pathfinder',
        ]);

        $create = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'customer_comments' => 'Noise when turning.',
            'recommendations' => 'Inspect power steering.',
            'payment_terms' => 'On Receipt',
            'order_status' => 'estimate',
            'workflow' => 'estimates',
            'authorized' => false,
            'services' => [
                [
                    'name' => 'Power Steering Diagnosis',
                    'notes' => 'Check fluid and pump.',
                    'authorized' => false,
                    'discount_percent' => 0,
                    'epa_percent' => 7,
                    'shop_supplies_percent' => 7,
                    'tax_percent' => 7,
                    'line_items' => [
                        [
                            'type' => 'labor',
                            'description' => 'Diagnostic labor',
                            'price' => 120,
                            'quantity' => 1,
                        ],
                        [
                            'type' => 'part',
                            'description' => 'Power steering fluid',
                            'price' => 18.5,
                            'quantity' => 2,
                        ],
                    ],
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('estimate.customer_id', $customer->id)
            ->assertJsonPath('estimate.vehicle_id', $vehicle->id)
            ->assertJsonPath('estimate.customer_comments', 'Noise when turning.')
            ->assertJsonPath('estimate.order_status', 'estimate')
            ->assertJsonPath('estimate.services.0.name', 'Power Steering Diagnosis')
            ->assertJsonPath('estimate.services.0.line_items.0.type', 'labor')
            ->assertJsonPath('estimate.totals.labor', '120.00')
            ->assertJsonPath('estimate.totals.parts', '37.00');

        $estimateId = $create->json('estimate.id');
        $this->assertDatabaseHas('estimates', [
            'id' => $estimateId,
            'number' => 1000,
            'service_writer_id' => $actor->id,
        ]);

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'po_number' => 'PO-42',
            'payment_terms' => 'Net 30',
            'order_status' => 'invoice',
            'workflow' => 'in_progress',
            'authorized' => true,
            'services' => [
                [
                    'name' => 'Updated Service',
                    'line_items' => [
                        [
                            'type' => 'fee',
                            'description' => 'Shop fee',
                            'price' => 25,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('estimate.po_number', 'PO-42')
            ->assertJsonPath('estimate.payment_terms', 'Net 30')
            ->assertJsonPath('estimate.order_status', 'invoice')
            ->assertJsonPath('estimate.is_authorized', true)
            ->assertJsonPath('estimate.services.0.name', 'Updated Service')
            ->assertJsonCount(1, 'estimate.services.0.line_items');

        $this->actingAs($actor)->deleteJson("/api/estimates/{$estimateId}")->assertOk();
        $this->assertDatabaseMissing('estimates', ['id' => $estimateId]);
    }

    public function test_vehicle_must_belong_to_customer(): void
    {
        $actor = $this->estimateUser(['estimates.create']);
        $customer = Customer::factory()->create();
        $otherVehicle = Vehicle::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/estimates', [
                'customer_id' => $customer->id,
                'vehicle_id' => $otherVehicle->id,
                'order_status' => 'estimate',
                'workflow' => 'estimates',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_create_requires_customer_and_status_fields(): void
    {
        $actor = $this->estimateUser(['estimates.create']);

        $this->actingAs($actor)
            ->postJson('/api/estimates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'order_status', 'workflow']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function estimateUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

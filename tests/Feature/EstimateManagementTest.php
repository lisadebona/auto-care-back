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
                            'remarks' => ['Not Completed'],
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
            ->assertJsonPath('estimate.services.0.line_items.0.remarks', ['Not Completed'])
            ->assertJsonPath('estimate.totals.labor', '120.00')
            ->assertJsonPath('estimate.totals.parts', '37.00')
            ->assertJsonPath('estimate.totals.subtotal', '157.00')
            ->assertJsonPath('estimate.totals.discount', '0.00')
            ->assertJsonPath('estimate.totals.shop_supplies', '10.99')
            ->assertJsonPath('estimate.totals.epa', '10.99')
            ->assertJsonPath('estimate.totals.tax', '12.53')
            ->assertJsonPath('estimate.totals.grand_total', '191.51')
            ->assertJsonPath('estimate.totals.paid_to_date', '0.00');

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
            ->assertJsonPath('estimate.workflow', 'invoices')
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

    public function test_blank_po_number_is_generated_from_the_record_id_and_estimate_number(): void
    {
        $actor = $this->estimateUser(['estimates.create', 'estimates.edit']);
        $customer = Customer::factory()->create();

        $created = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'po_number' => '   ',
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertCreated()
            ->assertJsonPath('estimate.po_number', 'PO11000')
            ->assertJsonPath('estimate.number', 1000);

        $estimateId = $created->json('estimate.id');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'po_number' => null,
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertOk()
            ->assertJsonPath('estimate.po_number', 'PO11000');
    }

    public function test_generated_po_number_skips_a_value_that_is_already_used(): void
    {
        $actor = $this->estimateUser(['estimates.create']);
        $customer = Customer::factory()->create();
        Estimate::factory()->create([
            'customer_id' => $customer->id,
            'number' => 1000,
            'po_number' => 'PO21001',
        ]);

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertCreated()
            ->assertJsonPath('estimate.id', 2)
            ->assertJsonPath('estimate.number', 1001)
            ->assertJsonPath('estimate.po_number', 'PO21002');
    }

    public function test_manual_po_number_is_stored_and_must_be_unique(): void
    {
        $actor = $this->estimateUser(['estimates.create', 'estimates.edit']);
        $customer = Customer::factory()->create();
        Estimate::factory()->create([
            'customer_id' => $customer->id,
            'po_number' => 'PO-42',
        ]);

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'po_number' => 'PO-42',
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'po_number' => 'The po number has already been taken.',
            ]);

        $created = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'po_number' => 'PO-99',
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertCreated()
            ->assertJsonPath('estimate.po_number', 'PO-99');

        $estimateId = $created->json('estimate.id');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'po_number' => 'PO-99',
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertOk()
            ->assertJsonPath('estimate.po_number', 'PO-99');
    }

    public function test_labor_can_be_assigned_to_one_technician(): void
    {
        $actor = $this->estimateUser(['estimates.create']);
        $customer = Customer::factory()->create();
        $technician = User::factory()->create([
            'name' => 'Alex Rivera',
            'hourly_rate' => '85.00',
            'flat_rate' => true,
        ]);
        $technician->assignRole(User::TECHNICIAN_ROLE);
        $writer = User::factory()->create();

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'estimates',
            'services' => [
                [
                    'name' => 'Brake service',
                    'line_items' => [
                        [
                            'type' => 'labor',
                            'description' => 'Replace pads',
                            'price' => 85,
                            'quantity' => 2,
                            'technician_id' => $technician->id,
                        ],
                    ],
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('estimate.services.0.line_items.0.technician_id', $technician->id)
            ->assertJsonPath('estimate.services.0.line_items.0.technician.name', 'Alex Rivera')
            ->assertJsonPath('estimate.services.0.line_items.0.price', '85.00')
            ->assertJsonPath('options.technicians.0.id', $technician->id)
            ->assertJsonPath('options.technicians.0.hourly_rate', '85.00')
            ->assertJsonPath('options.technicians.0.flat_rate', true);

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'estimates',
            'services' => [
                [
                    'line_items' => [
                        [
                            'type' => 'labor',
                            'technician_id' => $writer->id,
                        ],
                    ],
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'services.0.line_items.0.technician_id' => 'Select a technician.',
            ]);

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'estimates',
            'services' => [
                [
                    'line_items' => [
                        [
                            'type' => 'part',
                            'technician_id' => $technician->id,
                        ],
                    ],
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'services.0.line_items.0.technician_id' => 'A technician can only be assigned to labor.',
            ]);
    }

    public function test_workflow_accepts_the_shop_stages(): void
    {
        $actor = $this->estimateUser(['estimates.create']);
        $customer = Customer::factory()->create();

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'dropped_off',
        ])->assertCreated()
            ->assertJsonPath('estimate.workflow', 'dropped_off')
            ->assertJsonPath('options.workflows', [
                ['value' => 'estimates', 'label' => 'Estimates'],
                ['value' => 'dropped_off', 'label' => 'Dropped Off'],
                ['value' => 'in_progress', 'label' => 'In Progress / Repair Order'],
                ['value' => 'completed', 'label' => 'Invoiced / Complete'],
                ['value' => 'invoices', 'label' => 'Invoices'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ]);

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'not-a-stage',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['workflow' => 'The selected workflow is invalid.']);
    }

    public function test_repair_order_workflow_assigns_an_order_number(): void
    {
        $actor = $this->estimateUser(['estimates.view', 'estimates.create', 'estimates.edit']);
        $customer = Customer::factory()->create();

        $created = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertCreated()
            ->assertJsonPath('estimate.order_number', null);

        $estimateId = $created->json('estimate.id');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'in_progress',
        ])->assertOk()
            ->assertJsonPath('estimate.order_number', $estimateId.'001');

        $second = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'in_progress',
        ])->assertCreated();

        $secondId = $second->json('estimate.id');

        $second->assertJsonPath('estimate.order_number', $secondId.'002');

        $this->actingAs($actor)->putJson("/api/estimates/{$secondId}", [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'in_progress',
        ])->assertOk()
            ->assertJsonPath('estimate.order_number', $secondId.'002');

        $this->actingAs($actor)->getJson('/api/estimates?workflow=in_progress')
            ->assertOk()
            ->assertJsonCount(2, 'estimates');

        $this->actingAs($actor)->getJson('/api/estimates?status=estimate&except_workflow=in_progress')
            ->assertOk()
            ->assertJsonCount(0, 'estimates');
    }

    public function test_converting_an_estimate_assigns_an_invoice_number(): void
    {
        $actor = $this->estimateUser(['estimates.view', 'estimates.create', 'estimates.edit']);
        $customer = Customer::factory()->create();

        $created = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'estimates',
        ])->assertCreated()
            ->assertJsonPath('estimate.invoice_number', null);

        $estimateId = $created->json('estimate.id');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => 'estimates',
        ])->assertOk()
            ->assertJsonPath('estimate.invoice_number', $estimateId.'001');

        $second = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => 'estimates',
        ])->assertCreated();

        $secondId = $second->json('estimate.id');

        $second->assertJsonPath('estimate.invoice_number', $secondId.'002');

        $this->actingAs($actor)->putJson("/api/estimates/{$secondId}", [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => 'estimates',
        ])->assertOk()
            ->assertJsonPath('estimate.invoice_number', $secondId.'002');

        $this->actingAs($actor)->getJson('/api/estimates?status=invoice')
            ->assertOk()
            ->assertJsonCount(2, 'estimates');

        $this->actingAs($actor)->getJson('/api/estimates?status=estimate')
            ->assertOk()
            ->assertJsonCount(0, 'estimates');
    }

    public function test_converting_an_estimate_sets_the_workflow_to_invoices(): void
    {
        $actor = $this->estimateUser(['estimates.create', 'estimates.edit']);
        $customer = Customer::factory()->create();

        $created = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'dropped_off',
        ])->assertCreated()
            ->assertJsonPath('estimate.workflow', 'dropped_off');

        $estimateId = $created->json('estimate.id');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => 'dropped_off',
        ])->assertOk()
            ->assertJsonPath('estimate.workflow', 'invoices');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => 'completed',
        ])->assertOk()
            ->assertJsonPath('estimate.workflow', 'completed');

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => 'estimates',
        ])->assertCreated()
            ->assertJsonPath('estimate.workflow', 'invoices');

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'invoices',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['workflow' => 'Invoices is only available on an invoice.']);
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

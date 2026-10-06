<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomWorkflow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_manage_custom_workflows(): void
    {
        $this->getJson('/api/workflows')->assertForbidden();
        $this->postJson('/api/workflows', ['name' => 'Waiting on Parts'])->assertForbidden();
    }

    public function test_custom_workflow_can_be_created_renamed_and_deleted(): void
    {
        $actor = $this->workflowUser([
            'workflows.view',
            'workflows.create',
            'workflows.edit',
            'workflows.delete',
        ]);

        $created = $this->actingAs($actor)->postJson('/api/workflows', [
            'name' => 'Waiting on Parts',
        ])->assertCreated()
            ->assertJsonPath('name', 'Waiting on Parts');

        $workflowId = $created->json('id');

        $this->actingAs($actor)->putJson("/api/workflows/{$workflowId}", [
            'name' => 'Waiting on Customer',
        ])->assertOk()
            ->assertJsonPath('name', 'Waiting on Customer')
            ->assertJsonPath('value', $created->json('value'));

        $this->actingAs($actor)->getJson('/api/workflows')
            ->assertOk()
            ->assertJsonPath('0.name', 'Waiting on Customer');

        $this->actingAs($actor)->deleteJson("/api/workflows/{$workflowId}")
            ->assertOk();

        $this->assertDatabaseMissing('custom_workflows', ['id' => $workflowId]);
    }

    public function test_custom_workflow_name_cannot_match_a_system_workflow(): void
    {
        $actor = $this->workflowUser(['workflows.create']);

        $this->actingAs($actor)->postJson('/api/workflows', [
            'name' => 'In Progress / Repair Order',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'That workflow already exists.']);
    }

    public function test_custom_workflow_is_available_on_estimates_orders_and_invoices(): void
    {
        $actor = $this->workflowUser([
            'workflows.create',
            'estimates.view',
            'estimates.create',
            'estimates.edit',
        ]);
        $customer = Customer::factory()->create();

        $created = $this->actingAs($actor)->postJson('/api/workflows', [
            'name' => 'Waiting on Parts',
        ])->assertCreated();

        $value = $created->json('value');

        $estimate = $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => $value,
        ])->assertCreated()
            ->assertJsonPath('estimate.workflow', $value)
            ->assertJsonPath('estimate.workflow_label', 'Waiting on Parts')
            ->assertJsonFragment(['value' => $value, 'label' => 'Waiting on Parts']);

        $this->actingAs($actor)->getJson('/api/estimates?status=estimate&except_workflow=in_progress')
            ->assertOk()
            ->assertJsonPath('estimates.0.workflow_label', 'Waiting on Parts');

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => 'in_progress',
        ])->assertCreated()
            ->assertJsonPath('estimate.workflow_label', 'In Progress / Repair Order')
            ->assertJsonFragment(['value' => $value, 'label' => 'Waiting on Parts']);

        $estimateId = $estimate->json('estimate.id');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => $value,
        ])->assertOk()
            ->assertJsonPath('estimate.workflow', 'invoices');

        $this->actingAs($actor)->putJson("/api/estimates/{$estimateId}", [
            'customer_id' => $customer->id,
            'order_status' => 'invoice',
            'workflow' => $value,
        ])->assertOk()
            ->assertJsonPath('estimate.workflow', $value)
            ->assertJsonPath('estimate.workflow_label', 'Waiting on Parts')
            ->assertJsonFragment(['value' => $value, 'label' => 'Waiting on Parts']);

        $this->actingAs($actor)->getJson('/api/estimates?status=invoice')
            ->assertOk()
            ->assertJsonPath('estimates.0.workflow_label', 'Waiting on Parts');
    }

    public function test_custom_workflow_in_use_cannot_be_deleted(): void
    {
        $actor = $this->workflowUser([
            'workflows.create',
            'workflows.delete',
            'estimates.create',
        ]);
        $customer = Customer::factory()->create();
        $workflow = CustomWorkflow::factory()->create(['name' => 'Waiting on Parts']);

        $this->actingAs($actor)->postJson('/api/estimates', [
            'customer_id' => $customer->id,
            'order_status' => 'estimate',
            'workflow' => $workflow->value,
        ])->assertCreated();

        $this->actingAs($actor)->deleteJson("/api/workflows/{$workflow->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workflow' => 'This workflow is used on an estimate, order, or invoice.']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function workflowUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

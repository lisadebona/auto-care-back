<?php

namespace Tests\Feature;

use App\Models\CannedJob;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CannedJobManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_manage_canned_jobs(): void
    {
        $cannedJob = CannedJob::factory()->create();

        $this->getJson('/api/canned-jobs')->assertForbidden();
        $this->postJson('/api/canned-jobs', [])->assertForbidden();
        $this->getJson("/api/canned-jobs/{$cannedJob->id}")->assertForbidden();
        $this->putJson("/api/canned-jobs/{$cannedJob->id}", [])->assertForbidden();
        $this->deleteJson("/api/canned-jobs/{$cannedJob->id}")->assertForbidden();
    }

    public function test_estimate_editor_can_search_canned_jobs_for_an_estimate(): void
    {
        $cannedJob = CannedJob::factory()->create(['name' => 'Oil Change']);
        $editor = $this->cannedJobUser(['estimates.create']);

        $this->actingAs($editor)
            ->getJson('/api/canned-jobs?search=Oil')
            ->assertOk()
            ->assertJsonPath('canned_jobs.0.id', $cannedJob->id)
            ->assertJsonPath('canned_jobs.0.name', 'Oil Change');

        $this->actingAs($this->cannedJobUser(['customers.view']))
            ->getJson('/api/canned-jobs?search=Oil')
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_create_a_canned_job(): void
    {
        $actor = $this->cannedJobUser(['canned-jobs.view']);

        $this->actingAs($actor)
            ->postJson('/api/canned-jobs', [
                'name' => 'Oil Change',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('canned_jobs', ['name' => 'Oil Change']);
    }

    public function test_canned_job_requires_a_name(): void
    {
        $actor = $this->cannedJobUser(['canned-jobs.create']);

        $this->actingAs($actor)
            ->postJson('/api/canned-jobs', [
                'name' => '',
                'line_items' => [
                    ['type' => 'widget', 'price' => -1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'line_items.0.type', 'line_items.0.price']);
    }

    public function test_authorized_user_can_save_a_canned_job_with_service_line_items(): void
    {
        $actor = $this->cannedJobUser([
            'canned-jobs.view',
            'canned-jobs.create',
            'canned-jobs.edit',
            'canned-jobs.delete',
        ]);

        $this->actingAs($actor)->postJson('/api/canned-jobs', [
            'name' => 'Oil Change',
            'line_items' => [
                [
                    'type' => 'part',
                    'description' => 'OE-Quality Oil Filter',
                    'price' => '9.38',
                    'quantity' => '1',
                ],
                [
                    'type' => 'part',
                    'description' => 'Crush Washer / Drain Plug Seal',
                    'price' => '1.65',
                    'quantity' => '1',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('canned_job.name', 'Oil Change')
            ->assertJsonPath('canned_job.line_items.0.description', 'OE-Quality Oil Filter')
            ->assertJsonPath('canned_job.line_items.0.type', 'part')
            ->assertJsonPath('canned_job.line_items.0.subtotal', '9.38')
            ->assertJsonPath('canned_job.line_items.1.subtotal', '1.65')
            ->assertJsonPath('canned_job.subtotal', '11.03');

        $cannedJob = CannedJob::query()->where('name', 'Oil Change')->firstOrFail();

        $this->actingAs($actor)->putJson("/api/canned-jobs/{$cannedJob->id}", [
            'name' => 'Oil Change',
            'line_items' => [
                [
                    'type' => 'labor',
                    'description' => 'Oil change labor',
                    'price' => '45.00',
                    'quantity' => '0.5',
                    'discount' => '2.50',
                    'remarks' => ['Not Completed', 'Not Completed'],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('canned_job.line_items.0.type', 'labor')
            ->assertJsonPath('canned_job.line_items.0.subtotal', '20.00')
            ->assertJsonPath('canned_job.line_items.0.remarks.0', 'Not Completed')
            ->assertJsonCount(1, 'canned_job.line_items.0.remarks')
            ->assertJsonPath('canned_job.subtotal', '20.00');

        $this->assertDatabaseMissing('canned_job_line_items', [
            'canned_job_id' => $cannedJob->id,
            'description' => 'OE-Quality Oil Filter',
        ]);

        $this->actingAs($actor)
            ->getJson('/api/canned-jobs?search=Oil%20change%20labor')
            ->assertOk()
            ->assertJsonPath('canned_jobs.0.id', $cannedJob->id);

        $this->actingAs($actor)->deleteJson("/api/canned-jobs/{$cannedJob->id}")->assertOk();
        $this->assertDatabaseMissing('canned_jobs', ['id' => $cannedJob->id]);
        $this->assertDatabaseMissing('canned_job_line_items', ['canned_job_id' => $cannedJob->id]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function cannedJobUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

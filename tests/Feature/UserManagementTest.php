<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_or_update_users(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
        ])->assertUnauthorized();

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated',
            'email' => $user->email,
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_a_user(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/users', [
            'name' => 'Maya Chen',
            'email' => 'maya@example.com',
            'phone' => '+1 (555) 123-4567',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Maya Chen')
            ->assertJsonPath('email', 'maya@example.com')
            ->assertJsonPath('phone', '+1 (555) 123-4567')
            ->assertJsonMissingPath('password');

        $created = User::query()->where('email', 'maya@example.com')->first();

        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_create_requires_name_email_and_password(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_authenticated_user_can_update_a_user_without_changing_password(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create([
            'password' => 'original-password',
        ]);

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '555-0100',
            'password' => '',
        ])->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('email', 'updated@example.com')
            ->assertJsonPath('phone', '555-0100');

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertTrue(Hash::check('original-password', $user->password));
    }

    public function test_update_can_change_the_password_when_confirmed(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_update_rejects_an_unconfirmed_password(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_technicians_require_an_hourly_rate_and_can_save_flat_rate(): void
    {
        [$actor, $user] = $this->actorWhoCanAssignRoles();

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [User::TECHNICIAN_ROLE],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['hourly_rate']);

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [User::TECHNICIAN_ROLE],
            'hourly_rate' => '100.00',
            'flat_rate' => true,
        ])->assertOk()
            ->assertJsonPath('hourly_rate', '100.00')
            ->assertJsonPath('flat_rate', true);

        $user->refresh();

        $this->assertTrue($user->isTechnician());
        $this->assertSame('100.00', $user->hourly_rate);
        $this->assertTrue($user->flat_rate);
    }

    public function test_non_technicians_ignore_submitted_labor_rates(): void
    {
        [$actor, $user] = $this->actorWhoCanAssignRoles();

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['admin'],
            'hourly_rate' => '80.00',
            'flat_rate' => true,
        ])->assertOk()
            ->assertJsonPath('hourly_rate', null)
            ->assertJsonPath('flat_rate', false);

        $user->refresh();

        $this->assertNull($user->hourly_rate);
        $this->assertFalse($user->flat_rate);
    }

    public function test_removing_the_technician_role_clears_labor_rates(): void
    {
        [$actor, $user] = $this->actorWhoCanAssignRoles();
        $user->assignRole(User::TECHNICIAN_ROLE);
        $user->update([
            'hourly_rate' => '95.00',
            'flat_rate' => true,
        ]);

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['admin'],
            'hourly_rate' => '95.00',
            'flat_rate' => true,
        ])->assertOk()
            ->assertJsonPath('hourly_rate', null)
            ->assertJsonPath('flat_rate', false);

        $user->refresh();

        $this->assertFalse($user->isTechnician());
        $this->assertNull($user->hourly_rate);
        $this->assertFalse($user->flat_rate);
    }

    public function test_existing_technician_can_update_rates_without_resending_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(User::TECHNICIAN_ROLE);
        $user->update([
            'hourly_rate' => '40.00',
            'flat_rate' => false,
        ]);

        $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Tech',
            'email' => $user->email,
            'hourly_rate' => '55.50',
            'flat_rate' => true,
        ])->assertOk()
            ->assertJsonPath('hourly_rate', '55.50')
            ->assertJsonPath('flat_rate', true);

        $user->refresh();

        $this->assertTrue($user->isTechnician());
        $this->assertSame('55.50', $user->hourly_rate);
        $this->assertTrue($user->flat_rate);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function actorWhoCanAssignRoles(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create();
        $actor->givePermissionTo('roles.edit');

        return [$actor, User::factory()->create()];
    }
}

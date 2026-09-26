<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_manage_roles_or_permissions(): void
    {
        $this->getJson('/api/roles')->assertForbidden();
        $this->postJson('/api/roles', ['name' => 'advisor'])->assertForbidden();
        $this->getJson('/api/permissions')->assertForbidden();
        $this->postJson('/api/permissions', ['name' => 'invoices.view'])->assertForbidden();
    }

    public function test_users_without_permission_cannot_list_roles(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->getJson('/api/roles')->assertForbidden();
    }

    public function test_authorized_user_can_create_and_update_a_role(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(['roles.view', 'roles.create', 'roles.edit']);

        $this->actingAs($actor)->postJson('/api/roles', [
            'name' => 'advisor',
            'permissions' => ['users.view'],
        ])->assertCreated()
            ->assertJsonPath('name', 'advisor')
            ->assertJsonPath('permissions.0', 'users.view');

        $role = Role::findByName('advisor');

        $this->actingAs($actor)->putJson("/api/roles/{$role->id}", [
            'name' => 'service-advisor',
            'permissions' => ['users.view', 'users.edit'],
        ])->assertOk()
            ->assertJsonPath('name', 'service-advisor');

        $this->assertTrue($role->refresh()->hasPermissionTo('users.edit'));
    }

    public function test_super_admin_role_cannot_be_renamed_or_deleted(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super-admin');
        $role = Role::findByName('super-admin');

        $this->actingAs($actor)->putJson("/api/roles/{$role->id}", [
            'name' => 'owner',
            'permissions' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($actor)->deleteJson("/api/roles/{$role->id}")->assertForbidden();
        $this->assertNotNull(Role::findByName('super-admin'));
    }

    public function test_authorized_user_can_create_update_and_delete_a_permission(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(['permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete']);

        $this->actingAs($actor)->postJson('/api/permissions', [
            'name' => 'invoices.view',
        ])->assertCreated()
            ->assertJsonPath('name', 'invoices.view');

        $permission = Permission::findByName('invoices.view');

        $this->actingAs($actor)->putJson("/api/permissions/{$permission->id}", [
            'name' => 'invoices.read',
        ])->assertOk()
            ->assertJsonPath('name', 'invoices.read');

        $this->actingAs($actor)->deleteJson("/api/permissions/{$permission->id}")->assertOk();
        $this->assertNull(Permission::query()->where('name', 'invoices.read')->first());
    }
}

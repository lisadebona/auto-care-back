<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(['users', 'customers', 'vehicles', 'roles', 'permissions', 'products', 'brands', 'categories'])
            ->crossJoin(['view', 'create', 'edit', 'delete'])
            ->map(fn (array $pair) => Permission::findOrCreate(implode('.', $pair)));

        Permission::findOrCreate('general-settings.view');
        Permission::findOrCreate('general-settings.edit');
        Permission::findOrCreate('fees-and-rates.view');
        Permission::findOrCreate('fees-and-rates.edit');

        Role::findOrCreate(User::TECHNICIAN_ROLE);

        Role::findOrCreate('admin')->syncPermissions(
            $permissions->filter(fn (Permission $permission) => str_starts_with($permission->name, 'users.')),
        );

        Role::findOrCreate('super-admin')->syncPermissions(Permission::all());

        User::query()->whereDoesntHave('roles')->each(
            fn (User $user) => $user->assignRole('super-admin'),
        );
    }
}

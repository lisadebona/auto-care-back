<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'users_count' => $role->users_count,
                'created_at' => $role->created_at,
            ]);

        return response()->json([
            'roles' => $roles,
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Role::class, 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return response()->json($this->payload($role), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Role::class, 'name')->ignore($role->id),
                Rule::when($role->name === 'super-admin', [Rule::in(['super-admin'])]),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
        ], [
            'name.in' => 'The super-admin role cannot be renamed.',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return response()->json($this->payload($role));
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_if($role->name === 'super-admin', 403, 'The super-admin role cannot be deleted.');

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    /**
     * @return array{id: int, name: string, permissions: Collection<int, string>, users_count: int, created_at: mixed}
     */
    private function payload(Role $role): array
    {
        $role->load('permissions:id,name')->loadCount('users');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values(),
            'users_count' => $role->users_count,
            'created_at' => $role->created_at,
        ];
    }
}

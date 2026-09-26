<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission) => $this->payload($permission));

        return response()->json($permissions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Permission::class, 'name')],
        ]);

        $permission = Permission::create(['name' => $validated['name']]);

        return response()->json($this->payload($permission), 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Permission::class, 'name')->ignore($permission->id)],
        ]);

        $permission->update(['name' => $validated['name']]);

        return response()->json($this->payload($permission));
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully.']);
    }

    /**
     * @return array{id: int, name: string, roles_count: int, created_at: mixed}
     */
    private function payload(Permission $permission): array
    {
        $permission->loadCount('roles');

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'roles_count' => $permission->roles_count,
            'created_at' => $permission->created_at,
        ];
    }
}

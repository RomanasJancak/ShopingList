<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class AccessControlController extends Controller
{
    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->with(['roles:id,name,guard_name'])
            ->withCount('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name', 'created_at', 'updated_at']);

        return response()->json($permissions);
    }

    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->with([
                'permissions:id,name,guard_name',
            ])
            ->withCount([
                'permissions',
                'users',
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name', 'created_at', 'updated_at']);

        return response()->json($roles);
    }

    public function capabilities(Request $request): JsonResponse
    {
        return response()->json([
            'can_view_permissions' => $request->user()->can('permissions.view'),
            'can_manage_permissions' => $request->user()->can('permissions.manage'),
            'can_view_roles' => $request->user()->can('roles.view'),
            'can_manage_roles' => $request->user()->can('roles.manage'),
        ]);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $guardName = $validated['guard_name'] ?? 'web';

        $request->validate([
            'name' => [
                Rule::unique('permissions', 'name')->where('guard_name', $guardName),
            ],
        ]);

        $permission = Permission::query()->create([
            'name' => $validated['name'],
            'guard_name' => $guardName,
        ]);

        return response()->json($permission, 201);
    }

    public function updatePermission(Permission $permission, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', $request->input('guard_name', $permission->guard_name))
                    ->ignore($permission->id),
            ],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permission->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $permission->guard_name,
        ]);

        return response()->json($permission);
    }

    public function destroyPermission(Permission $permission): JsonResponse
    {
        $isUsedByRoles = $permission->roles()->exists();

        abort_if($isUsedByRoles, 422, 'Permission is assigned to roles and cannot be deleted.');

        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $guardName = $validated['guard_name'] ?? 'web';

        $request->validate([
            'name' => [
                Rule::unique('roles', 'name')->where('guard_name', $guardName),
            ],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => $guardName,
        ]);

        $permissionIds = $validated['permission_ids'] ?? [];
        if ($permissionIds !== []) {
            $role->permissions()->sync($permissionIds);
        }

        $role->load(['permissions:id,name,guard_name'])
            ->loadCount(['permissions', 'users']);

        return response()->json($role, 201);
    }

    public function updateRole(Role $role, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', $request->input('guard_name', $role->guard_name))
                    ->ignore($role->id),
            ],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        if ($role->name === 'super-admin' && ($validated['name'] ?? $role->name) !== 'super-admin') {
            abort(422, 'Super admin role name cannot be changed.');
        }

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $role->guard_name,
        ]);

        if (array_key_exists('permission_ids', $validated)) {
            $role->permissions()->sync($validated['permission_ids'] ?? []);
        }

        $role->load(['permissions:id,name,guard_name'])
            ->loadCount(['permissions', 'users']);

        return response()->json($role);
    }

    public function destroyRole(Role $role): JsonResponse
    {
        abort_if($role->name === 'super-admin', 422, 'Super admin role cannot be deleted.');

        $isUsedByUsers = $role->users()->exists();

        abort_if($isUsedByUsers, 422, 'Role is assigned to users and cannot be deleted.');

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }
}
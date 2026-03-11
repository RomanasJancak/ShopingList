<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
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
}
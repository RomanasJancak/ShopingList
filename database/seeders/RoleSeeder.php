<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('super-admin', 'web');

        $permissionsByRole = [
            'member' => [
                'profile.view',
            ],
            'manager' => [
                'profile.view',
                'family.manage',
                'roles.manage',
            ],
            'admin' => [
                'profile.view',
                'family.manage',
                'roles.manage',
                'users.manage',
            ],
        ];

        foreach ($permissionsByRole as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            foreach ($permissions as $permissionName) {
                $permission = Permission::findOrCreate($permissionName, 'web');
                $role->givePermissionTo($permission);
            }
        }
    }
}
<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccessControlVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_without_required_permissions_cannot_view_access_control_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($user);

        $this->getJson('/api/access-control/permissions')->assertForbidden();
        $this->getJson('/api/access-control/roles')->assertForbidden();
    }

    public function test_user_with_permissions_can_view_permissions_and_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        $this->actingAs($user);

        $this->getJson('/api/access-control/permissions')
            ->assertOk()
            ->assertJsonFragment(['name' => 'permissions.view'])
            ->assertJsonFragment(['name' => 'roles.view']);

        $this->getJson('/api/access-control/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'manager'])
            ->assertJsonFragment(['name' => 'admin']);
    }

    public function test_super_admin_can_view_permissions_and_roles(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        // Keep super-admin fully synced in case role assignments changed in test setup.
        $superAdminRole = Role::query()->where('name', 'super-admin')->firstOrFail();
        $superAdminRole->syncPermissions(Permission::query()->pluck('name')->all());

        $this->actingAs($superAdmin);

        $this->getJson('/api/access-control/permissions')->assertOk();
        $this->getJson('/api/access-control/roles')->assertOk();
    }

    public function test_only_users_with_permissions_manage_can_create_update_and_delete_permissions(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager);

        $this->postJson('/api/access-control/permissions', [
            'name' => 'temp.permission',
            'guard_name' => 'web',
        ])->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $permissionId = $this->postJson('/api/access-control/permissions', [
            'name' => 'temp.permission',
            'guard_name' => 'web',
        ])->assertCreated()->json('id');

        $this->putJson("/api/access-control/permissions/{$permissionId}", [
            'name' => 'temp.permission.updated',
            'guard_name' => 'web',
        ])->assertOk()
            ->assertJsonFragment(['name' => 'temp.permission.updated']);

        $this->deleteJson("/api/access-control/permissions/{$permissionId}")
            ->assertOk();
    }

    public function test_user_with_roles_manage_can_create_update_and_delete_role_when_not_assigned(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $permission = Permission::findOrCreate('role.test.permission', 'web');

        $this->actingAs($manager);

        $roleId = $this->postJson('/api/access-control/roles', [
            'name' => 'ops-role',
            'guard_name' => 'web',
            'permission_ids' => [$permission->id],
        ])->assertCreated()->json('id');

        $this->putJson("/api/access-control/roles/{$roleId}", [
            'name' => 'ops-role-updated',
            'guard_name' => 'web',
            'permission_ids' => [$permission->id],
        ])->assertOk()
            ->assertJsonFragment(['name' => 'ops-role-updated']);

        $this->deleteJson("/api/access-control/roles/{$roleId}")
            ->assertOk();
    }

    public function test_role_cannot_be_deleted_while_assigned_to_user(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $assignedUser = User::factory()->create();
        $role = Role::findOrCreate('support-role', 'web');
        $assignedUser->assignRole($role);

        $this->actingAs($manager);

        $this->deleteJson("/api/access-control/roles/{$role->id}")
            ->assertStatus(422);
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdminRole = Role::query()->where('name', 'super-admin')->firstOrFail();

        $this->actingAs($admin);

        $this->deleteJson("/api/access-control/roles/{$superAdminRole->id}")
            ->assertStatus(422);
    }
}
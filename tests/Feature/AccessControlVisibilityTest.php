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
}
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyRoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_family_owner_can_create_custom_roles_and_assign_them(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $owner->assignRole('super-admin');

        $this->actingAs($owner);

        $familyResponse = $this->postJson('/api/families', [
            'name' => 'Household',
        ]);

        $familyResponse->assertCreated();
        $familyId = $familyResponse->json('id');

        $this->postJson("/api/families/{$familyId}/roles", [
            'name' => 'viewer',
            'level' => 5,
            'permissions' => ['list.view'],
        ])->assertCreated();

        $roleResponse = $this->postJson("/api/families/{$familyId}/roles", [
            'name' => 'editor',
            'level' => 10,
            'permissions' => ['list.edit'],
        ]);

        $roleResponse->assertCreated();

        $this->postJson("/api/families/{$familyId}/assign-role", [
            'user_id' => $member->id,
            'family_role_id' => $roleResponse->json('id'),
        ])->assertOk();

        $this->actingAs($member);

        $permissionsResponse = $this->getJson("/api/families/{$familyId}/permissions/me");

        $permissionsResponse->assertOk()
            ->assertJsonFragment(['family_id' => $familyId])
            ->assertJsonFragment(['name' => 'editor'])
            ->assertJsonFragment(['effective_permissions' => ['list.view', 'list.edit']]);
    }

    public function test_lower_role_user_cannot_assign_higher_role(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $target = User::factory()->create();

        $owner->assignRole('super-admin');
        $manager->assignRole('manager');

        $this->actingAs($owner);

        $familyId = $this->postJson('/api/families', [
            'name' => 'Team',
        ])->json('id');

        $lowRoleId = $this->postJson("/api/families/{$familyId}/roles", [
            'name' => 'team-manager',
            'level' => 10,
            'permissions' => ['roles.manage'],
        ])->json('id');

        $highRoleId = $this->postJson("/api/families/{$familyId}/roles", [
            'name' => 'team-admin',
            'level' => 20,
            'permissions' => ['roles.manage', 'team.manage'],
        ])->json('id');

        $this->postJson("/api/families/{$familyId}/assign-role", [
            'user_id' => $manager->id,
            'family_role_id' => $lowRoleId,
        ])->assertOk();

        $this->actingAs($manager);

        $this->postJson("/api/families/{$familyId}/assign-role", [
            'user_id' => $target->id,
            'family_role_id' => $highRoleId,
        ])->assertForbidden();
    }

    public function test_logged_in_user_without_family_cannot_create_family(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        $this->postJson('/api/families', [
            'name' => 'Blocked Family',
        ])->assertForbidden();
    }

    public function test_family_management_endpoints_support_full_crud_and_member_changes(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $owner->assignRole('super-admin');

        $this->actingAs($owner);

        $familyId = $this->postJson('/api/families', [
            'name' => 'Initial Family',
        ])->assertCreated()->json('id');

        $viewerRoleId = $this->postJson("/api/families/{$familyId}/roles", [
            'name' => 'viewer',
            'level' => 5,
            'permissions' => ['list.view'],
        ])->assertCreated()->json('id');

        $editorRoleId = $this->postJson("/api/families/{$familyId}/roles", [
            'name' => 'editor',
            'level' => 10,
            'permissions' => ['list.edit'],
        ])->assertCreated()->json('id');

        $this->postJson("/api/families/{$familyId}/members", [
            'user_id' => $member->id,
            'family_role_id' => $viewerRoleId,
        ])->assertCreated();

        $this->getJson("/api/families/{$familyId}/members")
            ->assertOk()
            ->assertJsonFragment(['user_id' => $member->id]);

        $this->putJson("/api/families/{$familyId}/members/{$member->id}", [
            'family_role_id' => $editorRoleId,
        ])->assertOk()
            ->assertJsonFragment(['family_role_id' => $editorRoleId]);

        $this->putJson("/api/families/{$familyId}/roles/{$viewerRoleId}", [
            'name' => 'viewer-renamed',
            'level' => 6,
            'permissions' => ['list.view', 'list.comment'],
        ])->assertOk()
            ->assertJsonFragment(['name' => 'viewer-renamed'])
            ->assertJsonFragment(['level' => 6]);

        $this->putJson("/api/families/{$familyId}", [
            'name' => 'Updated Family',
        ])->assertOk()
            ->assertJsonFragment(['name' => 'Updated Family']);

        $this->deleteJson("/api/families/{$familyId}/members/{$member->id}")
            ->assertOk();

        $this->deleteJson("/api/families/{$familyId}/roles/{$viewerRoleId}")
            ->assertOk();

        $this->deleteJson("/api/families/{$familyId}")
            ->assertOk();
    }

    public function test_family_owner_role_cannot_be_changed_or_removed(): void
    {
        $owner = User::factory()->create();

        $owner->assignRole('super-admin');

        $this->actingAs($owner);

        $familyId = $this->postJson('/api/families', [
            'name' => 'Owner Family',
        ])->assertCreated()->json('id');

        $roleId = $this->postJson("/api/families/{$familyId}/roles", [
            'name' => 'role-a',
            'level' => 5,
            'permissions' => ['list.view'],
        ])->assertCreated()->json('id');

        $this->putJson("/api/families/{$familyId}/members/{$owner->id}", [
            'family_role_id' => $roleId,
        ])->assertStatus(422);

        $this->deleteJson("/api/families/{$familyId}/members/{$owner->id}")
            ->assertStatus(422);
    }
}
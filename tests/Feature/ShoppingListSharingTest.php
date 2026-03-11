<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyRole;
use App\Models\FamilyUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_list_and_share_it_directly_and_through_family(): void
    {
        $owner = User::factory()->create();
        $directUser = User::factory()->create();
        $familyMember = User::factory()->create();

        $family = Family::factory()->forOwner($owner)->create();
        $memberRole = FamilyRole::create([
            'family_id' => $family->id,
            'name' => 'member',
            'level' => 10,
            'permissions' => ['profile.view'],
        ]);

        FamilyUserRole::create([
            'family_id' => $family->id,
            'user_id' => $familyMember->id,
            'family_role_id' => $memberRole->id,
        ]);

        $this->actingAs($owner);

        $shoppingListId = $this->postJson('/api/shopping-lists', [
            'name' => 'Weekend groceries',
            'description' => 'Food and household items',
        ])->assertCreated()->json('id');

        $this->postJson("/api/shopping-lists/{$shoppingListId}/users", [
            'user_id' => $directUser->id,
            'permission' => 'edit',
        ])->assertCreated();

        $this->postJson("/api/shopping-lists/{$shoppingListId}/families", [
            'family_id' => $family->id,
            'permission' => 'view',
        ])->assertCreated();

        $this->postJson("/api/shopping-lists/{$shoppingListId}/families/{$family->id}/members", [
            'user_id' => $familyMember->id,
            'permission' => 'edit',
        ])->assertCreated();

        $this->actingAs($familyMember);

        $this->getJson("/api/shopping-lists/{$shoppingListId}")
            ->assertOk()
            ->assertJsonFragment(['effective_permission' => 'edit']);

        $this->putJson("/api/shopping-lists/{$shoppingListId}", [
            'name' => 'Weekend groceries updated',
            'description' => 'Updated by family member',
        ])->assertOk()
            ->assertJsonFragment(['name' => 'Weekend groceries updated']);

        $this->actingAs($directUser);

        $this->getJson('/api/shopping-lists')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $shoppingListId,
                'effective_permission' => 'edit',
            ]);
    }

    public function test_family_wide_view_permission_allows_view_but_not_edit(): void
    {
        $owner = User::factory()->create();
        $familyMember = User::factory()->create();

        $family = Family::factory()->forOwner($owner)->create();
        $memberRole = FamilyRole::create([
            'family_id' => $family->id,
            'name' => 'member',
            'level' => 10,
            'permissions' => ['profile.view'],
        ]);

        FamilyUserRole::create([
            'family_id' => $family->id,
            'user_id' => $familyMember->id,
            'family_role_id' => $memberRole->id,
        ]);

        $this->actingAs($owner);

        $shoppingListId = $this->postJson('/api/shopping-lists', [
            'name' => 'Family only list',
        ])->assertCreated()->json('id');

        $this->postJson("/api/shopping-lists/{$shoppingListId}/families", [
            'family_id' => $family->id,
            'permission' => 'view',
        ])->assertCreated();

        $this->actingAs($familyMember);

        $this->getJson("/api/shopping-lists/{$shoppingListId}")
            ->assertOk()
            ->assertJsonFragment(['effective_permission' => 'view']);

        $this->putJson("/api/shopping-lists/{$shoppingListId}", [
            'name' => 'No edit allowed',
        ])->assertForbidden();
    }

    public function test_non_owner_cannot_manage_shares_or_delete_list(): void
    {
        $owner = User::factory()->create();
        $directUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner);

        $shoppingListId = $this->postJson('/api/shopping-lists', [
            'name' => 'Protected list',
        ])->assertCreated()->json('id');

        $this->postJson("/api/shopping-lists/{$shoppingListId}/users", [
            'user_id' => $directUser->id,
            'permission' => 'edit',
        ])->assertCreated();

        $this->actingAs($directUser);

        $this->postJson("/api/shopping-lists/{$shoppingListId}/users", [
            'user_id' => $otherUser->id,
            'permission' => 'view',
        ])->assertForbidden();

        $this->deleteJson("/api/shopping-lists/{$shoppingListId}")
            ->assertForbidden();
    }
}